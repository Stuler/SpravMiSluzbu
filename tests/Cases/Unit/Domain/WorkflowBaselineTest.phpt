<?php declare(strict_types=1);

use App\Domain\City\City;
use App\Domain\Demand\Demand;
use App\Domain\Matching\ProviderInvitation;
use App\Domain\Provider\Provider;
use Tester\Assert;

require __DIR__ . '/../../../../vendor/autoload.php';
Tester\Environment::setup();

// No container, database, network or mail: capture behavior before refactoring.
$city = new City();
$demand = new Demand(str_repeat('a', 32), str_repeat('b', 64), 'Clean a two-bedroom apartment.',
    $city, null, 'Next week', 'Customer', 'customer@example.test', null, null);
Assert::same(Demand::PENDING, $demand->getStatus());
$demand->verify();
$verifiedAt = $demand->getVerifiedAt();
$demand->verify();
Assert::same($verifiedAt, $demand->getVerifiedAt());
Assert::same(Demand::READY, $demand->getStatus());
$lease = $demand->claimProcessing();
Assert::true($demand->ownsLease($lease));
$demand->close();
$demand->close();
$demand->verify();
Assert::same(Demand::CLOSED, $demand->getStatus());
Assert::false($demand->ownsLease($lease));
Assert::exception(fn() => $demand->requeue(null), InvalidArgumentException::class);

// Invitations preserve the first response, even when the repeated answer differs.
$provider = (new ReflectionClass(Provider::class))->newInstanceWithoutConstructor();
$invitation = new ProviderInvitation($demand, $provider, 'Relevant service.');
$invitation->answer(true);
$invitation->answer(false);
Assert::same('accepted', $invitation->getResponse());
$invitation->startDelivery();
$invitation->markDeliveryUnknown();
Assert::false($invitation->canRetryDelivery());
Assert::exception(fn() => $invitation->retryDelivery(), InvalidArgumentException::class);

echo "Workflow baseline passed.\n";
