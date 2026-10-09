<?php declare(strict_types=1);

// Transient SQLite by default; explicit --local-mysql-docker uses a disposable local database.
// This suite never loads local.neon, calls external APIs or sends real mail.
require __DIR__ . '/../../../../vendor/autoload.php';
require __DIR__ . '/../../../../app/bootstrapConstants.php';
\Tester\Environment::setup();
date_default_timezone_set('Europe/Prague');

use App\Domain\CategoryService\CategoryService;
use App\Domain\City\City;
use App\Domain\Customer\CustomerAccountFacade;
use App\Domain\Customer\CustomerToken;
use App\Domain\Demand\Demand;
use App\Domain\Demand\DemandFacade;
use App\Domain\District\District;
use App\Domain\LoginRole\LoginRole;
use App\Domain\Region\Region;
use App\Domain\StateUser\StateUser;
use App\Domain\User\User;
use App\Model\Database\EntityManagerDecorator;
use App\Model\Mail\MailSender;
use App\Model\Security\RateLimit\PublicRateLimiter;
use Contributte\Mailing\IMailBuilderFactory;
use Contributte\Mailing\IMailSender;
use Contributte\Mailing\MailBuilder;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Tester\Assert;

$config = ORMSetup::createAttributeMetadataConfiguration([APP_DIR . '/Domain'], true, null,
    \Doctrine\Common\Cache\Psr6\CacheAdapter::wrap(new \Doctrine\Common\Cache\ArrayCache()));
$config->setNamingStrategy(new \Doctrine\ORM\Mapping\UnderscoreNamingStrategy());
$config->setAutoGenerateProxyClasses(\Doctrine\Common\Proxy\AbstractProxyFactory::AUTOGENERATE_EVAL);
$mysqlDatabase = in_array('--local-mysql-docker', $argv, true) ? new \Tests\Toolkit\LocalMysqlDatabase() : null;
$em = new EntityManagerDecorator(EntityManager::create($mysqlDatabase?->parameters() ?? ['driver' => 'pdo_sqlite', 'memory' => true], $config));
$classes = [\App\Domain\Job\Job::class, \App\Domain\Review\Review::class, User::class, LoginRole::class, StateUser::class, Region::class, District::class, City::class, CategoryService::class, CustomerToken::class, Demand::class,
    \App\Domain\Provider\Provider::class, \App\Domain\StateProvider\StateProvider::class, \App\Domain\ProviderRegion\ProviderRegion::class,
    \App\Domain\ProviderServiceCategory\ProviderServiceCategory::class, \App\Domain\Matching\ProviderInvitation::class, \App\Domain\Matching\AiRun::class, \App\Domain\Admin\AdminAudit::class,
    \App\Domain\Subscription\Subscription::class, \App\Domain\SubscriptionPlan\SubscriptionPlan::class, \App\Domain\StateSubscription\StateSubscription::class];
$metadata = array_map(fn($class) => $em->getClassMetadata($class), $classes);
(new SchemaTool($em))->createSchema($metadata);
foreach ($metadata as $mapping) {
    // Legacy provider join entities declare inverse collections that are not mapped yet.
    if (in_array($mapping->name, [\App\Domain\ProviderRegion\ProviderRegion::class, \App\Domain\ProviderServiceCategory\ProviderServiceCategory::class], true)) { continue; }
    Assert::same([], (new \Doctrine\ORM\Tools\SchemaValidator($em))->validateClass($mapping));
}

$sender = new class implements IMailSender {
    public array $messages = [];
    public bool $fail = false;
    public function send(MailBuilder $builder): void {
        if ($this->fail) { throw new RuntimeException('Simulated ambiguous SMTP delivery.'); }
        // Compile and render the real email, while retaining the link for test navigation.
        $builder->getTemplate()->renderToString();
        $this->messages[] = $builder->getTemplate()->getParameters();
    }
    public function token(): string {
        parse_str(parse_url($this->messages[array_key_last($this->messages)]['link'], PHP_URL_QUERY), $query);
        return $query['token'];
    }
};
$factory = new class($sender) implements IMailBuilderFactory {
    public function __construct(private IMailSender $sender) {}
    public function create(): MailBuilder {
        $builder = new MailBuilder($this->sender);
        $template = new \Nette\Bridges\ApplicationLatte\DefaultTemplate(new \Latte\Engine());
        $template->_config = (object) ['layout' => false];
        $builder->setTemplate($template);
        return $builder;
    }
};
$links = new \Nette\Application\LinkGenerator((new \App\Model\Router\RouterFactory())->create(), new \Nette\Http\UrlScript('https://test.invalid/'));
$accounts = new CustomerAccountFacade($em, new MailSender($factory), $links);
$demandRepository = new \App\Infrastructure\Persistence\Doctrine\DoctrineDemandRepository($em);
$providerRepository = new \App\Infrastructure\Persistence\Doctrine\DoctrineProviderRepository($em);
$invitationRepository = new \App\Infrastructure\Persistence\Doctrine\DoctrineInvitationRepository($em);
$demands = new DemandFacade($em, $accounts, new MailSender($factory), $links, $demandRepository);
$invitations = new \App\Domain\Matching\InvitationFacade($em, new MailSender($factory), $links, $invitationRepository, $demandRepository, $providerRepository);
foreach (['fresh', 'active', 'blocked'] as $name) {
    $state = new StateUser($name); $state->setDateCreated(new DateTime()); $em->persist($state);
}
$role = new LoginRole(); $role->setName(LoginRole::ROLE_MEMBER); $em->persist($role);
$adminRole = new LoginRole(); $adminRole->setName(LoginRole::ROLE_ADMIN); $em->persist($adminRole);
$region = new Region(); $region->setName('Test region'); $region->setShortcut('TT'); $region->setUse(true); $em->persist($region);
$district = new District(); $district->setName('Test district'); $district->setVehRegNum('TT'); $district->setCode(1); $district->setRegion($region); $district->setUse(true); $em->persist($district);
$city = new City(); $city->setFullname('Test city'); $city->setShortname('Test'); $city->setZip('90001'); $city->setRegion($region); $city->setDistrict($district); $city->setUse(true); $em->persist($city);
$category = new CategoryService('Cleaning'); $em->persist($category); $em->flush();

// Registration: no required address, normalized unique email, fresh customer role.
$accounts->register('Jana', '  JANA@example.test ', 'long-password');
$raw = $sender->token();
$customer = $em->getRepository(User::class)->findOneBy(['email' => 'jana@example.test']);
Assert::type(User::class, $customer);
Assert::false($customer->isActivated());
Assert::same('member', $customer->getLoginRole()->getName());
Assert::true($accounts->isValidToken($raw, CustomerToken::ACTIVATE));
Assert::false($accounts->isValidToken($raw, CustomerToken::RESET));
Assert::false($customer->isActivated()); // GET lookup does not activate.
$count = count($sender->messages);
$accounts->register('Other', 'JANA@example.test', 'long-password');
Assert::same($count, count($sender->messages));
$accounts->activate($raw);
Assert::true($customer->isActivated());
Assert::false($accounts->isValidToken($raw, CustomerToken::ACTIVATE));
Assert::exception(fn() => $accounts->activate($raw), InvalidArgumentException::class);

// Unknown login is a normal authentication failure, not Doctrine NoResultException.
$auth = new \App\Model\Security\Authenticator\UserAuthenticator(new \App\Model\Database\QueryManager($em), $em, \App\Model\Security\Passwords::create());
Assert::exception(fn() => $auth->authenticate('unknown@example.test', 'anything'), \Nette\Security\AuthenticationException::class);
Assert::same('customer', $auth->authenticate(' JANA@example.test ', 'long-password')->getAccountType());

// Password recovery replaces older tokens and invalidates existing customer sessions.
$oldVersion = $customer->getCustomerSessionVersion();
$accounts->requestToken('jana@example.test', CustomerToken::RESET); $oldReset = $sender->token();
$accounts->requestToken('jana@example.test', CustomerToken::RESET); $reset = $sender->token();
Assert::false($accounts->isValidToken($oldReset, CustomerToken::RESET));
$accounts->resetPassword($reset, 'a-new-password');
Assert::true(\App\Model\Security\Passwords::create()->verify('a-new-password', $customer->getPasswordHash()));
Assert::null($accounts->findActiveCustomer($customer->getId(), $oldVersion));
Assert::false($accounts->isValidToken($reset, CustomerToken::RESET));
Assert::exception(fn() => $accounts->resetPassword($reset, 'another-password'), InvalidArgumentException::class);

// Expired account tokens cannot activate an account or reset a password.
$accounts->register('Expired', 'expired@example.test', 'long-password');
$expiredActivation = $sender->token();
$expiredToken = $em->getRepository(CustomerToken::class)->findOneBy(['tokenHash' => hash('sha256', $expiredActivation)]);
(new ReflectionProperty(CustomerToken::class, 'expiresAt'))->setValue($expiredToken, new DateTimeImmutable('-1 second'));
Assert::false($accounts->isValidToken($expiredActivation, CustomerToken::ACTIVATE));
Assert::exception(fn() => $accounts->activate($expiredActivation), InvalidArgumentException::class);
$accounts->requestToken('jana@example.test', CustomerToken::RESET);
$expiredReset = $sender->token();
$expiredToken = $em->getRepository(CustomerToken::class)->findOneBy(['tokenHash' => hash('sha256', $expiredReset)]);
(new ReflectionProperty(CustomerToken::class, 'expiresAt'))->setValue($expiredToken, new DateTimeImmutable('-1 second'));
Assert::exception(fn() => $accounts->resetPassword($expiredReset, 'another-password'), InvalidArgumentException::class);

// Guest demand: no synthetic user, no automatic email verification, no access by reference alone.
$data = ['description' => 'Please clean my two-bedroom apartment.', 'cityId' => $city->getId(), 'categoryId' => $category->getId(), 'requestedTerm' => 'Next week', 'contactName' => 'Guest', 'contactEmail' => 'GUEST@example.test', 'contactPhone' => ''];
$key = bin2hex(random_bytes(32));
$guest = $demands->create($data, null, $key); $access = $sender->token();
Assert::same(Demand::PENDING, $guest->getStatus());
Assert::null($guest->getCustomer());
Assert::same($guest, $demands->findByToken($access));
Assert::same(Demand::PENDING, $guest->getStatus());
Assert::false($demands->canAccess($guest, $customer, null));
Assert::false($demands->canAccess($guest, null, hash('sha256', 'wrong')));
Assert::true($demands->canAccess($guest, null, hash('sha256', $access)));
$count = count($sender->messages);
Assert::same($guest, $demands->create($data, null, $key));
Assert::same($count, count($sender->messages));
Assert::exception(fn() => $demands->changeStatus($guest, $customer, null, true), InvalidArgumentException::class);
$demands->changeStatus($guest, null, hash('sha256', $access), false);
Assert::same(Demand::READY, $guest->getStatus());
$demands->changeStatus($guest, null, hash('sha256', $access), true);
$demands->changeStatus($guest, null, hash('sha256', $access), false);
Assert::same(Demand::CLOSED, $guest->getStatus()); // Verification cannot reopen a closed demand.

// Recovery requires reference plus matching email, rotates the bearer token and its session grant.
$demands->requestAccess($guest->getReference(), 'wrong@example.test');
Assert::same($count, count($sender->messages));
$demands->requestAccess($guest->getReference(), 'guest@example.test'); $newAccess = $sender->token();
Assert::null($demands->findByToken($access));
Assert::false($demands->canAccess($guest, null, hash('sha256', $access)));
Assert::same($guest, $demands->findByToken($newAccess));
(new ReflectionProperty(Demand::class, 'accessExpiresAt'))->setValue($guest, new DateTimeImmutable('-1 second'));
Assert::null($demands->findByToken($newAccess));

// An authenticated customer's contact must come from the verified account, never submitted email.
$owned = $demands->create($data, $customer, bin2hex(random_bytes(32)));
Assert::same('jana@example.test', $owned->getContactEmail());
Assert::same(Demand::READY, $owned->getStatus());
Assert::true($demands->canAccess($owned, $customer, null));
Assert::false($demands->canAccess($owned, null, null));
$accounts->register('Other', 'other@example.test', 'long-password'); $accounts->activate($sender->token());
$other = $em->getRepository(User::class)->findOneBy(['email' => 'other@example.test']);
Assert::false($demands->canAccess($owned, $other, null));
Assert::count(1, $demands->forCustomer($customer));
Assert::count(0, $demands->forCustomer($other));
Assert::exception(fn() => $demands->create(array_replace($data, ['cityId' => 999999]), null, bin2hex(random_bytes(32))), InvalidArgumentException::class);
$category->setDateDeleted(new DateTime());
Assert::exception(fn() => $demands->create($data, null, bin2hex(random_bytes(32))), InvalidArgumentException::class);
$customer->setStateUser($em->find(StateUser::class, 3));
Assert::false($demands->canAccess($owned, $customer, null));

// Customer and provider identities with the same numeric ID must remain distinct.
$security = new \App\Model\Security\SecurityUser(new \Tests\Fixtures\Dummy\DummyUserStorage());
$security->setAuthorizator(new \App\Model\Security\Authorizator\StaticAuthorizator());
$security->login(new \App\Model\Security\Identity($customer->getId(), ['provider'], ['accountType' => 'provider']));
Assert::false($security->isCustomer());
Assert::true($security->isProvider());

// Persistent rate limiting works across separate service instances.
$directory = sys_get_temp_dir() . '/bripeon-rate-test-' . bin2hex(random_bytes(8));
$limiter = new PublicRateLimiter($directory);
try {
    Assert::true($limiter->consume('test-ip', 1, 3600));
    Assert::false((new PublicRateLimiter($directory))->consume('test-ip', 1, 3600));
} finally {
    foreach (glob($directory . '/*.json') as $file) { unlink($file); }
    rmdir($directory);
}

require __DIR__ . '/customer-ui-checks.php';
require __DIR__ . '/../Matching/matching-checks.php';
require __DIR__ . '/../Subscription/subscription-checks.php';
ob_start();
require __DIR__ . '/../Admin/admin-checks.php';
require __DIR__ . '/../Review/review-checks.php';
require __DIR__ . '/../DDD/transaction-checks.php';
require __DIR__ . '/../DDD/registration-conflict-checks.php';
echo "Customer, demand, matching, invitation and UI checks passed.\n";
ob_end_flush();
