<?php declare(strict_types=1);

// Explicitly opt-in, local Docker only. Never reads application configuration or sends mail.
require __DIR__ . '/../../../../vendor/autoload.php';

use App\Domain\Admin\AdminAudit;
use App\Domain\Admin\AdminAuditFacade;
use App\Domain\CategoryService\CategoryService;
use App\Domain\CategoryService\CategoryServiceFacade;
use App\Domain\LoginRole\LoginRole;
use App\Domain\StateUser\StateUser;
use App\Domain\User\User;
use App\Model\Database\EntityManagerDecorator;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Nette\Utils\ArrayHash;
use Tester\Assert;

function connectionParameters(?string $database = null): array
{
    return ['driver' => 'pdo_mysql', 'host' => 'database', 'port' => 3306, 'user' => 'root', 'password' => 'root',
        'charset' => 'utf8mb4'] + ($database === null ? [] : ['dbname' => $database]);
}

function manager(string $database): EntityManagerDecorator
{
    $config = \Tests\Toolkit\IsolatedEntityManager::create()->getConfiguration();
    $em = new EntityManagerDecorator(EntityManager::create(connectionParameters($database), $config));
    $em->getConnection()->executeStatement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $em->getConnection()->executeStatement('SET SESSION innodb_lock_wait_timeout = 10');
    return $em;
}

function catalogue(EntityManagerDecorator $em, int $actorId): CategoryServiceFacade
{
    $security = \Tests\Toolkit\DddServiceFixtures::security();
    $security->setAuthorizator(new \App\Model\Security\Authorizator\StaticAuthorizator());
    $security->login($em->find(User::class, $actorId)->toIdentity());
    return new CategoryServiceFacade($em, new AdminAuditFacade($em, $security));
}

if (($argv[1] ?? '') === '--worker') {
    $database = $argv[2] ?? '';
    if (!is_file('/.dockerenv') || !preg_match('/^bripeon_admin_test_[a-f0-9]{12}$/D', $database)) { exit(2); }
    $em = manager($database);
    $facade = catalogue($em, (int) $argv[3]);
    try {
        $em->wrapInTransaction(function () use ($em, $facade, $argv): void {
            // Establish an older consistent snapshot before the other process commits.
            $em->getRepository(CategoryService::class)->findAll();
            echo "READY\n"; flush();
            if ($argv[6] === 'retire') { $facade->deleteCategory((int) $argv[4]); }
            else { $facade->saveCategory(ArrayHash::from(['id' => (int) $argv[4], 'name' => 'Moved', 'parent_id' => (int) $argv[5], 'description' => ''])); }
        });
        echo "SAVED\n";
    } catch (\InvalidArgumentException $e) { echo "REJECTED\n"; }
    exit;
}

\Tester\Environment::setup();
if (($argv[1] ?? '') !== '--local-docker' || !is_file('/.dockerenv')) {
    \Tester\Environment::skip('Opt in inside local Docker with --local-docker; creates and drops only a randomly named synthetic test database.');
}
$database = 'bripeon_admin_test_' . bin2hex(random_bytes(6));
$server = DriverManager::getConnection(connectionParameters());
$server->executeStatement('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$process = null;
$em = null;
try {
    $db = DriverManager::getConnection(connectionParameters($database));
    // Minimal legacy schema: execute the actual admin migration, including both conversion statements.
    foreach (['provider' => 'state_provider_id', 'user' => 'state_user_id'] as $table => $stateColumn) {
        $db->executeStatement("CREATE TABLE `$table` (id INT AUTO_INCREMENT PRIMARY KEY, $stateColumn INT NOT NULL, date_activated DATETIME NULL) ENGINE=InnoDB");
        foreach ([[3, null], [3, '2026-01-01 12:00:00'], [1, null], [2, null], [2, '2026-01-01 12:00:00']] as [$state, $date]) {
            $db->insert($table, [$stateColumn => $state, 'date_activated' => $date]);
        }
    }
    $migration = new \Database\Migrations\Version20261007140000($db, new \Psr\Log\NullLogger());
    $migration->up(new \Doctrine\DBAL\Schema\Schema());
    foreach ($migration->getSql() as $query) { $db->executeStatement($query->getStatement(), $query->getParameters(), $query->getTypes()); }
    foreach (['provider' => 'state_provider_id', 'user' => 'state_user_id'] as $table => $stateColumn) {
        $rows = $db->fetchAllAssociative("SELECT $stateColumn AS state, administratively_blocked AS blocked FROM `$table` ORDER BY id");
        Assert::same([1, 2, 1, 2, 2], array_map('intval', array_column($rows, 'state')));
        Assert::same([1, 1, 0, 0, 0], array_map('intval', array_column($rows, 'blocked')));
    }
    Assert::same(5, (int) $db->fetchOne('SELECT COUNT(*) FROM provider WHERE session_version = 1'));
    echo "Legacy block conversion passed on MySQL.\n";
    foreach (['admin_audit', 'provider', 'user'] as $table) { $db->executeStatement("DROP TABLE `$table`"); }

    $em = manager($database);
    $classes = [User::class, LoginRole::class, StateUser::class, CategoryService::class, AdminAudit::class];
    (new SchemaTool($em))->createSchema(array_map(fn($class) => $em->getClassMetadata($class), $classes));
    foreach (['fresh', 'active', 'blocked'] as $name) {
        $state = new StateUser($name); $state->setDateCreated(new DateTime()); $em->persist($state);
    }
    $role = new LoginRole(); $role->setName('admin'); $em->persist($role); $em->flush();
    $actor = new User('Test', 'Admin', 'admin@example.test', 'unused', $role, $em->find(StateUser::class, 2), null, null, null, '');
    $em->persist($actor); $em->flush();
    $facade = catalogue($em, $actor->getId());

    foreach (['move', 'retire'] as $action) {
        $a = new CategoryService('A'); $b = new CategoryService('B'); $em->persist($a); $em->persist($b); $em->flush();
        $aId = $a->getId(); $bId = $b->getId();
        $workerPipes = [];
        $em->wrapInTransaction(function () use ($em, $facade, $actor, $database, $aId, $bId, $action, &$process, &$workerPipes): void {
            $em->createQueryBuilder()->select('c')->from(CategoryService::class, 'c')->orderBy('c.id', 'ASC')
                ->getQuery()->setLockMode(LockMode::PESSIMISTIC_WRITE)->getResult();
            $pipes = [];
            $process = proc_open([PHP_BINARY, __FILE__, '--worker', $database, (string) $actor->getId(), (string) $bId, (string) $aId, $action],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            if (!is_resource($process)) { throw new RuntimeException('Could not start competing connection.'); }
            fclose($pipes[0]); stream_set_timeout($pipes[1], 15);
            Assert::same("READY\n", fgets($pipes[1]));
            $workerPipes = $pipes;
            $facade->saveCategory(ArrayHash::from(['id' => $aId, 'name' => 'A', 'parent_id' => $bId, 'description' => '']));
        });
        $pipes = $workerPipes;
        $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]); $exit = proc_close($process); $process = null;
        Assert::same('', $error); Assert::same(0, $exit);
        Assert::same($action === 'move' ? "REJECTED\n" : "SAVED\n", $output);
        $em->clear();
        $a = $em->find(CategoryService::class, $aId); $b = $em->find(CategoryService::class, $bId);
        Assert::same($bId, $a->getParent()->getId()); Assert::null($b->getParent());
        if ($action === 'retire') { Assert::notNull($a->getDateDeleted()); Assert::notNull($b->getDateDeleted()); }
        $facade = catalogue($em, $actor->getId());
        echo "Concurrent category $action passed on MySQL REPEATABLE READ.\n";
    }
} finally {
    if (is_resource($process)) { proc_terminate($process); proc_close($process); }
    if ($em !== null) { $em->getConnection()->close(); }
    if (isset($db)) { $db->close(); }
    // The exact database name was generated by this invocation; no application schema is touched.
    $server->executeStatement('DROP DATABASE `' . $database . '`');
    $server->close();
}
