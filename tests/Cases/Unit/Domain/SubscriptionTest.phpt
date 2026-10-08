<?php declare(strict_types=1);

use App\Domain\Provider\Provider;
use App\Domain\StateSubscription\StateSubscription;
use App\Domain\Subscription\Subscription;
use App\Domain\SubscriptionPlan\SubscriptionPlan;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaValidator;
use Tester\Assert;

require __DIR__ . '/../../../../vendor/autoload.php';
Tester\Environment::setup();

// Metadata validation does not connect to a database or load local.neon.
$config = ORMSetup::createAttributeMetadataConfiguration([__DIR__ . '/../../../../app/Domain'], true, null,
    Doctrine\Common\Cache\Psr6\CacheAdapter::wrap(new Doctrine\Common\Cache\ArrayCache()));
$em = EntityManager::create(['driver' => 'pdo_sqlite', 'memory' => true], $config);
foreach ([Subscription::class, StateSubscription::class, SubscriptionPlan::class] as $class) {
    Assert::same([], (new SchemaValidator($em))->validateClass($em->getClassMetadata($class)));
}

$provider = (new ReflectionClass(Provider::class))->newInstanceWithoutConstructor();
$plan = new SubscriptionPlan('Monthly', 'month', 1, 1500, 'price_test');
$state = new StateSubscription('pending');
$now = new DateTimeImmutable('2026-10-08 12:00:00');
$subscription = new Subscription($provider, 'sub_test', $plan, $state, $now);
Assert::same($state, $subscription->getStatus());
Assert::same([$subscription], $state->getSubscriptions());
Assert::same($now, $subscription->getStartedAt());
Assert::equal(DateTime::createFromImmutable($now), $subscription->getDateCreated());
Assert::exception(fn() => new Subscription($provider, '', $plan, $state), InvalidArgumentException::class);
Assert::exception(fn() => new SubscriptionPlan('Invalid', 'month', 0, -1, 'price_test'), InvalidArgumentException::class);
echo "Subscription model and mapping passed.\n";
