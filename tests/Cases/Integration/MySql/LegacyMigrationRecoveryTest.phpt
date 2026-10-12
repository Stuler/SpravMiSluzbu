<?php declare(strict_types=1);

require __DIR__ . '/../../../../vendor/autoload.php';

use Doctrine\DBAL\DriverManager;
use Doctrine\Migrations\Metadata\Storage\TableMetadataStorage;
use Doctrine\Migrations\Metadata\Storage\TableMetadataStorageConfiguration;
use Doctrine\Migrations\Version\AlphabeticalComparator;
use Tester\Assert;
use Tests\Toolkit\LocalMysqlDatabase;

Tester\Environment::setup();
if (($argv[1] ?? '') !== '--local-docker') {
    Tester\Environment::skip('Opt in with --local-docker; uses only a disposable local MySQL database.');
}

$database = new LocalMysqlDatabase();
$db = DriverManager::getConnection($database->parameters());
try {
    // Import schema only. Never import the dump's account or location data.
    $dump = file_get_contents(__DIR__ . '/../../../../db/dump/2025_09_04.sql');
    preg_match_all('/CREATE TABLE `[^`]+` \(.*?\) ENGINE=.*?;/s', $dump, $matches);
    Assert::true(count($matches[0]) > 10);
    $db->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($matches[0] as $sql) { $db->executeStatement($sql); }
    $db->executeStatement('SET FOREIGN_KEY_CHECKS = 1');

    // Reproduce the inspected partial legacy migration and missing history.
    $tables = ['category_service', 'provider_region', 'provider_service_category', 'state_provider', 'state_user', 'user'];
    foreach ($tables as $table) {
        $schema = $db->createSchemaManager()->introspectTable($table);
        foreach ($schema->getForeignKeys() as $key) {
            if ($key->getLocalColumns() === ['deleted_by']) {
                $db->executeStatement('ALTER TABLE ' . $db->quoteIdentifier($table) . ' DROP FOREIGN KEY ' . $db->quoteIdentifier($key->getName()));
            }
        }
        foreach ($schema->getIndexes() as $index) {
            if ($index->getColumns() === ['deleted_by']) {
                $db->executeStatement('ALTER TABLE ' . $db->quoteIdentifier($table) . ' DROP INDEX ' . $db->quoteIdentifier($index->getName()));
            }
        }
        $db->executeStatement('ALTER TABLE ' . $db->quoteIdentifier($table) . ' MODIFY deleted_by VARCHAR(255) DEFAULT NULL');
    }
    $db->executeStatement("INSERT INTO category_service (name, description) VALUES ('Synthetic category', 'Preserve this history')");

    $sql = file_get_contents(__DIR__ . '/../../../../db/ManualMigrations/20261012-rosti-beta-history.sql');
    $sql = preg_replace('/^--.*$/m', '', $sql);
    foreach (explode(';', $sql) as $statement) {
        if (trim($statement) !== '') { $db->executeStatement($statement); }
    }
    Assert::same('Preserve this history', $db->fetchOne('SELECT description FROM category_service'));
    foreach ($tables as $table) {
        $schema = $db->createSchemaManager()->introspectTable($table);
        Assert::type(Doctrine\DBAL\Types\IntegerType::class, $schema->getColumn('deleted_by')->getType());
        $keys = array_filter($schema->getForeignKeys(), static fn ($key): bool => $key->getLocalColumns() === ['deleted_by'] && $key->getForeignTableName() === 'user');
        Assert::count(1, $keys);
    }
    $config = new TableMetadataStorageConfiguration();
    $config->setTableName('doctrine_migrations');
    $storage = new TableMetadataStorage($db, new AlphabeticalComparator(), $config);
    Assert::count(10, $storage->getExecutedMigrations()->getItems());

    // Only the five new migrations remain; execute their actual SQL in order.
    $executed = $db->fetchFirstColumn('SELECT version FROM doctrine_migrations');
    $pending = [];
    foreach (glob(__DIR__ . '/../../../../db/Migrations/Version*.php') as $file) {
        $class = 'Database\\Migrations\\' . basename($file, '.php');
        if (in_array($class, $executed, true)) { continue; }
        $pending[] = $class;
        $migration = new $class($db, new Psr\Log\NullLogger());
        $migration->up(new Doctrine\DBAL\Schema\Schema());
        foreach ($migration->getSql() as $query) {
            $db->executeStatement($query->getStatement(), $query->getParameters(), $query->getTypes());
        }
    }
    Assert::count(5, $pending);
    Assert::true($db->createSchemaManager()->tablesExist(['demand', 'provider_invitation', 'admin_audit', 'job', 'review']));
    Assert::same('Preserve this history', $db->fetchOne('SELECT description FROM category_service'));
    echo "Legacy repair preserves data, restores ten historical versions and permits all five new migrations.\n";
} finally {
    $db->close();
    $database->cleanup();
}
