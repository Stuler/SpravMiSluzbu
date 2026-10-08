<?php declare(strict_types = 1);

namespace Tests\Cases\Integration\Provider;

use App\Domain\CategoryService\CategoryService;
use App\Domain\City\City;
use App\Domain\LoginRole\LoginRole;
use App\Domain\Provider\ActivationToken;
use App\Domain\Provider\DTO\ProviderRegistrationDataFactory;
use App\Domain\Provider\Exception\DuplicateEmailException;
use App\Domain\Provider\Exception\InvalidActivationTokenException;
use App\Domain\Provider\Exception\RegistrationValidationException;
use App\Domain\Provider\Provider;
use App\Domain\Provider\ProviderFacade;
use App\Domain\ProviderRegion\ProviderRegion;
use App\Domain\ProviderServiceCategory\ProviderServiceCategory;
use App\Domain\Region\Region;
use App\Domain\StateProvider\StateProvider;
use App\Domain\StateProvider\StateProviderRepository;
use App\Model\Database\EntityManagerDecorator;
use App\Bootstrap;
use Tester\Assert;

require_once __DIR__ . '/../../../bootstrap.php';

$container = Bootstrap::boot()
	->addConfig(__DIR__ . '/../../../config/mailer.neon')
	->createContainer();
/** @var EntityManagerDecorator $em */
$em = $container->getByType(EntityManagerDecorator::class);
/** @var ProviderFacade $facade */
$facade = $container->getByType(ProviderFacade::class);

$cityId = $em->getRepository(City::class)->findAll()[0]->getId();
$regionId = $em->getRepository(Region::class)->findAll()[0]->getId();
$categoryId = $em->getRepository(CategoryService::class)->findAll()[0]->getId();

$payload = static function (array $override = []) use ($cityId, $regionId, $categoryId): array {
	return array_merge([
		'serviceCategories' => [['value' => $categoryId, 'label' => 'X']],
		'coveredRegions' => [['value' => $regionId, 'label' => 'Y']],
		'firstName' => 'Janko',
		'lastName' => 'Hraško',
		'email' => 'PROV_' . uniqid('', true) . '@test.local',
		'password' => 'secret123',
		'confirmPassword' => 'secret123',
		'phone' => '+421900000000',
		'companyName' => 'Hraško s.r.o.',
		'ico' => '12345678',
		'street' => 'Hlavná',
		'streetNumber' => '12',
		'cityId' => (string) $cityId,
		'city' => 'X',
		'zip' => '81101',
		'usePersonalAsContact' => true,
		'contactFirstName' => '',
		'contactLastName' => '',
	], $override);
};

/**
 * Run a closure inside a DB transaction that is always rolled back, isolating the test.
 */
$inRollback = static function (callable $fn) use ($em): void {
	$em->getConnection()->beginTransaction();
	try {
		$fn();
	} finally {
		if ($em->getConnection()->isTransactionActive()) {
			$em->getConnection()->rollBack();
		}
		$em->clear();
	}
};

/**
 * Persist a fresh provider with a controlled activation token (so the raw token is known).
 */
$makeFreshProvider = static function (string $email, string $rawToken, \DateTime $expiresAt) use ($em, $cityId): Provider {
	$city = $em->getRepository(City::class)->find($cityId);
	$role = $em->getRepository(LoginRole::class)->findOneBy(['name' => LoginRole::ROLE_PROVIDER]);
	$state = $em->getRepository(StateProvider::class)->find(StateProviderRepository::STATE_FRESH);

	$provider = new Provider(
		companyName: 'Test s.r.o.',
		contactName: 'Janko',
		contactSurname: 'Hraško',
		contactTitle: '',
		email: $email,
		phoneNumber: '+421900000000',
		ico: '',
		dic: '',
		password: \App\Model\Security\Passwords::create()->hash('secret123'),
		streetNo: 'Hlavná 12',
		city: $city,
		zipCode: '81101',
		stateProvider: $state,
		loginRole: $role,
	);
	$provider->setActivationToken(ActivationToken::hashRaw($rawToken), $expiresAt);
	$em->persist($provider);
	$em->flush();

	return $provider;
};

// Successful registration: fresh state, token hash stored, relations created.
$inRollback(function () use ($facade, $em, $payload, $regionId, $categoryId): void {
	$dto = ProviderRegistrationDataFactory::fromArray($payload());
	$result = $facade->register($dto);

	Assert::true($result->providerId > 0);

	$provider = $em->getRepository(Provider::class)->find($result->providerId);
	Assert::type(Provider::class, $provider);
	Assert::true($provider->isFresh());
	Assert::notNull($provider->getActivationTokenHash());
	Assert::same(64, strlen((string) $provider->getActivationTokenHash()));
	Assert::notNull($provider->getActivationTokenExpiresAt());
	Assert::null($provider->getDateActivated());

	$regions = $em->getRepository(ProviderRegion::class)->findBy(['provider' => $provider]);
	$categories = $em->getRepository(ProviderServiceCategory::class)->findBy(['provider' => $provider]);
	Assert::count(1, $regions);
	Assert::count(1, $categories);
	Assert::same($regionId, $regions[0]->getRegion()->getId());
	Assert::same($categoryId, $categories[0]->getCategoryService()->getId());
});

// Email is stored normalized (lowercased + trimmed).
$inRollback(function () use ($facade, $em, $payload): void {
	$dto = ProviderRegistrationDataFactory::fromArray($payload(['email' => '  MixedCase' . uniqid('', true) . '@Test.LOCAL  ']));
	$result = $facade->register($dto);
	Assert::same(strtolower(trim($result->email)), $result->email);

	$provider = $em->getRepository(Provider::class)->find($result->providerId);
	Assert::same($result->email, $provider->getEmail());
});

// Duplicate normalized email is rejected.
$inRollback(function () use ($facade, $payload): void {
	$email = 'dup_' . uniqid('', true) . '@test.local';
	$facade->register(ProviderRegistrationDataFactory::fromArray($payload(['email' => $email])));

	Assert::exception(
		fn() => $facade->register(ProviderRegistrationDataFactory::fromArray($payload(['email' => strtoupper($email)]))),
		DuplicateEmailException::class
	);
});

// Server-side validation rejects incomplete data and short passwords.
$inRollback(function () use ($facade, $payload): void {
	Assert::exception(
		fn() => $facade->register(ProviderRegistrationDataFactory::fromArray($payload(['companyName' => '']))),
		RegistrationValidationException::class
	);
	Assert::exception(
		fn() => $facade->register(ProviderRegistrationDataFactory::fromArray($payload(['password' => 'short', 'confirmPassword' => 'short']))),
		RegistrationValidationException::class
	);
});

// Invalid category, region or city are rejected.
$inRollback(function () use ($facade, $payload): void {
	Assert::exception(
		fn() => $facade->register(ProviderRegistrationDataFactory::fromArray($payload(['serviceCategories' => [['value' => 999999, 'label' => 'X']]]))),
		RegistrationValidationException::class
	);
	Assert::exception(
		fn() => $facade->register(ProviderRegistrationDataFactory::fromArray($payload(['coveredRegions' => [['value' => 999999, 'label' => 'X']]]))),
		RegistrationValidationException::class
	);
	Assert::exception(
		fn() => $facade->register(ProviderRegistrationDataFactory::fromArray($payload(['cityId' => '999999999']))),
		RegistrationValidationException::class
	);
});

// IČO is optional: registration succeeds without it.
$inRollback(function () use ($facade, $em, $payload): void {
	$result = $facade->register(ProviderRegistrationDataFactory::fromArray($payload(['ico' => ''])));
	$provider = $em->getRepository(Provider::class)->find($result->providerId);
	Assert::same('', $provider->getIco());
});

// Looking up a fresh provider by token must NOT mutate state (mirrors the activation GET).
$inRollback(function () use ($facade, $em, $makeFreshProvider): void {
	$raw = 'rawtoken-' . bin2hex(random_bytes(8));
	$provider = $makeFreshProvider('lookup_' . uniqid('', true) . '@test.local', $raw, (new \DateTime())->modify('+1 hour'));

	$found = $facade->findFreshProviderByRawToken($raw);
	Assert::type(Provider::class, $found);
	Assert::same($provider->getId(), $found->getId());

	$em->refresh($provider);
	Assert::true($provider->isFresh());
	Assert::null($provider->getDateActivated());
	Assert::notNull($provider->getActivationTokenHash());
});

// Valid activation: state becomes activated, date stamped, token cleared.
$inRollback(function () use ($facade, $em, $makeFreshProvider): void {
	$raw = 'rawtoken-' . bin2hex(random_bytes(8));
	$provider = $makeFreshProvider('act_' . uniqid('', true) . '@test.local', $raw, (new \DateTime())->modify('+1 hour'));

	$activated = $facade->activate($raw);
	Assert::true($activated->isActivated());
	Assert::notNull($activated->getDateActivated());
	Assert::null($activated->getActivationTokenHash());
	Assert::null($activated->getActivationTokenExpiresAt());
});

// Reused token: a second activation attempt fails.
$inRollback(function () use ($facade, $makeFreshProvider): void {
	$raw = 'rawtoken-' . bin2hex(random_bytes(8));
	$makeFreshProvider('reuse_' . uniqid('', true) . '@test.local', $raw, (new \DateTime())->modify('+1 hour'));

	$facade->activate($raw);
	Assert::exception(fn() => $facade->activate($raw), InvalidActivationTokenException::class);
});

// Expired token: lookup returns null and activation fails.
$inRollback(function () use ($facade, $makeFreshProvider): void {
	$raw = 'rawtoken-' . bin2hex(random_bytes(8));
	$makeFreshProvider('exp_' . uniqid('', true) . '@test.local', $raw, (new \DateTime())->modify('-1 hour'));

	Assert::null($facade->findFreshProviderByRawToken($raw));
	Assert::exception(fn() => $facade->activate($raw), InvalidActivationTokenException::class);
});

// Invalid/unknown token: lookup returns null and activation fails.
$inRollback(function () use ($facade): void {
	Assert::null($facade->findFreshProviderByRawToken('does-not-exist'));
	Assert::null($facade->findFreshProviderByRawToken(''));
	Assert::exception(fn() => $facade->activate('does-not-exist'), InvalidActivationTokenException::class);
});

// Resend rotates the token once the cooldown has passed, and is blocked within the cooldown.
$inRollback(function () use ($facade, $em, $makeFreshProvider): void {
	$email = 'resend_' . uniqid('', true) . '@test.local';
	$raw = 'rawtoken-' . bin2hex(random_bytes(8));
	$provider = $makeFreshProvider($email, $raw, (new \DateTime())->modify('+1 hour'));
	$originalHash = $provider->getActivationTokenHash();

	// Within cooldown (just created): no rotation.
	$facade->resendActivation($email);
	$em->refresh($provider);
	Assert::same($originalHash, $provider->getActivationTokenHash());

	// Move the last-send timestamp into the past, then resend rotates the token.
	$em->getConnection()->executeStatement(
		'UPDATE provider SET activation_sent_at = ? WHERE id = ?',
		[(new \DateTime())->modify('-10 minutes')->format('Y-m-d H:i:s'), $provider->getId()]
	);
	$em->refresh($provider);

	$facade->resendActivation($email);
	$em->refresh($provider);
	Assert::notSame($originalHash, $provider->getActivationTokenHash());
	Assert::true($provider->isFresh());
});

// Resend for an unknown email is a silent no-op (no enumeration, no exception).
$inRollback(function () use ($facade): void {
	Assert::noError(fn() => $facade->resendActivation('nobody_' . uniqid('', true) . '@test.local'));
});

echo "OK\n";
