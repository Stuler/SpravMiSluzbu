<?php declare(strict_types = 1);

namespace Tests\Cases\Integration\Provider;

use App\Bootstrap;
use App\Domain\City\City;
use App\Domain\LoginRole\LoginRole;
use App\Domain\Provider\Exception\ProviderBlockedException;
use App\Domain\Provider\Exception\ProviderNotActivatedException;
use App\Domain\Provider\Provider;
use App\Domain\StateProvider\StateProvider;
use App\Domain\StateProvider\StateProviderRepository;
use App\Domain\StateUser\StateUser;
use App\Domain\StateUser\StateUserRepository;
use App\Domain\User\User;
use App\Model\Database\EntityManagerDecorator;
use App\Model\Exception\Runtime\AuthenticationException;
use App\Model\Security\Authenticator\ProviderAuthenticator;
use App\Model\Security\Authenticator\UserAuthenticator;
use App\Model\Security\Identity;
use App\Model\Security\Passwords;
use Tester\Assert;

require_once __DIR__ . '/../../../bootstrap.php';

$container = Bootstrap::boot()
	->addConfig(__DIR__ . '/../../../config/mailer.neon')
	->createContainer();
/** @var EntityManagerDecorator $em */
$em = $container->getByType(EntityManagerDecorator::class);
/** @var ProviderAuthenticator $auth */
$auth = $container->getByType(ProviderAuthenticator::class);

$cityId = $em->getRepository(City::class)->findAll()[0]->getId();

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

$makeProvider = static function (string $email, int $stateId, string $password = 'secret123') use ($em, $cityId): Provider {
	$city = $em->getRepository(City::class)->find($cityId);
	$role = $em->getRepository(LoginRole::class)->findOneBy(['name' => LoginRole::ROLE_PROVIDER]);
	$state = $em->getRepository(StateProvider::class)->find($stateId);

	$provider = new Provider(
		companyName: 'Test s.r.o.',
		contactName: 'Janko',
		contactSurname: 'Hraško',
		contactTitle: '',
		email: $email,
		phoneNumber: '+421900000000',
		ico: '',
		dic: '',
		password: Passwords::create()->hash($password),
		streetNo: 'Hlavná 12',
		city: $city,
		zipCode: '81101',
		stateProvider: $state,
		loginRole: $role,
	);
	$em->persist($provider);
	$em->flush();

	return $provider;
};

// Correct credentials for an activated provider return a provider identity.
$inRollback(function () use ($auth, $makeProvider): void {
	$email = 'auth_' . uniqid('', true) . '@test.local';
	$provider = $makeProvider($email, StateProviderRepository::STATE_ACTIVATED, 'secret123');

	$identity = $auth->authenticate($email, 'secret123');

	Assert::type(Identity::class, $identity);
	Assert::same($provider->getId(), $identity->getId());
	Assert::contains(LoginRole::ROLE_PROVIDER, $identity->getRoles());
	Assert::true($identity->isProvider());
	Assert::same(Identity::ACCOUNT_TYPE_PROVIDER, $identity->getAccountType());
	Assert::same(StateProviderRepository::STATE_ACTIVATED, $identity->getData()['state']);
	Assert::same($email, $identity->getData()['email']);
});

// Email is normalized: a differently-cased email still authenticates.
$inRollback(function () use ($auth, $makeProvider): void {
	$email = 'casecheck_' . uniqid('', true) . '@test.local';
	$makeProvider($email, StateProviderRepository::STATE_ACTIVATED, 'secret123');

	$identity = $auth->authenticate(strtoupper($email), 'secret123');
	Assert::same($email, $identity->getData()['email']);
});

// Wrong password is rejected generically.
$inRollback(function () use ($auth, $makeProvider): void {
	$email = 'wrongpass_' . uniqid('', true) . '@test.local';
	$makeProvider($email, StateProviderRepository::STATE_ACTIVATED, 'secret123');

	Assert::exception(fn() => $auth->authenticate($email, 'wrong-password'), AuthenticationException::class);
});

// Unknown email is rejected generically.
$inRollback(function () use ($auth): void {
	Assert::exception(
		fn() => $auth->authenticate('nobody_' . uniqid('', true) . '@test.local', 'secret123'),
		AuthenticationException::class
	);
});

// A fresh (unactivated) provider with correct credentials is told activation is required.
$inRollback(function () use ($auth, $makeProvider): void {
	$email = 'fresh_' . uniqid('', true) . '@test.local';
	$makeProvider($email, StateProviderRepository::STATE_FRESH, 'secret123');

	Assert::exception(fn() => $auth->authenticate($email, 'secret123'), ProviderNotActivatedException::class);
});

// A blocked provider is rejected even with correct credentials.
$inRollback(function () use ($auth, $makeProvider): void {
	$email = 'blocked_' . uniqid('', true) . '@test.local';
	$makeProvider($email, StateProviderRepository::STATE_BLOCKED, 'secret123');

	Assert::exception(fn() => $auth->authenticate($email, 'secret123'), ProviderBlockedException::class);
});

// dateLastLogin is updated on successful authentication.
$inRollback(function () use ($auth, $em, $makeProvider): void {
	$email = 'lastlogin_' . uniqid('', true) . '@test.local';
	$provider = $makeProvider($email, StateProviderRepository::STATE_ACTIVATED, 'secret123');

	$em->getConnection()->executeStatement(
		'UPDATE provider SET date_last_login = ? WHERE id = ?',
		['2000-01-01 00:00:00', $provider->getId()]
	);
	$em->refresh($provider);

	$auth->authenticate($email, 'secret123');
	$em->refresh($provider);
	Assert::true($provider->getDateLastLogin() > new \DateTime('2001-01-01'));
});

// Coexistence: a user and a provider may share the same email and authenticate independently.
$inRollback(function () use ($auth, $em, $makeProvider): void {
	$email = 'shared_' . uniqid('', true) . '@test.local';

	// Provider account with one password.
	$makeProvider($email, StateProviderRepository::STATE_ACTIVATED, 'provider-pass-123');

	// User account with the same email but a different password.
	$role = $em->getRepository(LoginRole::class)->findOneBy(['name' => LoginRole::ROLE_MEMBER]);
	$userState = $em->getRepository(StateUser::class)->find(StateUserRepository::STATE_ACTIVATED);
	$user = new User(
		name: 'Janko',
		surname: 'Hraško',
		email: $email,
		password: Passwords::create()->hash('user-pass-123'),
		loginRole: $role,
		stateUser: $userState,
		streetNo: 'Hlavná 12',
		city: 'Bratislava',
		zipCode: '81101',
		hash: 'legacy-hash',
	);
	$em->persist($user);
	$em->flush();

	// Provider authenticator works with provider credentials.
	$providerIdentity = $auth->authenticate($email, 'provider-pass-123');
	Assert::same(Identity::ACCOUNT_TYPE_PROVIDER, $providerIdentity->getAccountType());

	// Provider authenticator rejects the user's password.
	Assert::exception(fn() => $auth->authenticate($email, 'user-pass-123'), AuthenticationException::class);

	// The default user authenticator works with the user's credentials independently.
	$userAuthenticator = new UserAuthenticator(
		new \App\Model\Database\QueryManager($em),
		$em,
		Passwords::create()
	);
	$userIdentity = $userAuthenticator->authenticate($email, 'user-pass-123');
	Assert::same($user->getId(), $userIdentity->getId());
});

echo "OK\n";
