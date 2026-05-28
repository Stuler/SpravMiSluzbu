<?php declare(strict_types=1);

namespace App\Domain\Provider;

use App\Domain\CategoryService\CategoryService;
use App\Domain\City\City;
use App\Domain\LoginRole\LoginRole;
use App\Domain\Provider\DTO\ProviderRegistrationData;
use App\Domain\ProviderRegion\ProviderRegion;
use App\Domain\ProviderServiceCategory\ProviderServiceCategory;
use App\Domain\Region\Region;
use App\Domain\StateProvider\StateProvider;
use App\Domain\StateProvider\StateProviderRepository;
use App\Domain\StateUser\StateUserRepository;
use App\Model\Database\EntityManagerDecorator;
use App\Model\Exception\Logic\InvalidArgumentException;
use App\Model\Exception\Logic\UserAlreadyActiveException;
use App\Model\Mail\MailSender;
use App\Model\Security\Passwords;
use Exception;
use Nette\Application\LinkGenerator;

readonly class ProviderFacade
{


	public function __construct(
		private EntityManagerDecorator $em,
		private MailSender             $mailSender,
		private LinkGenerator          $linkGenerator,
	)
	{
	}

	/**
	 * @param array<string, mixed> $data
	 * @throws Exception
	 */
	public function createProvider(array $data): Provider
	{
		$this->validateInputs($data);
		$regionIds = $data['region'];
		$serviceCategoryIds = $data['serviceCategory'];
		if (!is_array($regionIds) || !is_array($serviceCategoryIds)) {
			throw new Exception('Invalid provider category or region data.');
		}

		$loginRole = $this->stringData($data, 'role', LoginRole::ROLE_PROVIDER);
		$loginRoleEntity = $this->em->getRepository(LoginRole::class)->findOneBy(['name' => $loginRole]);
		$stateProvider = $this->resolveInitialProviderState();
		$city = $this->em->getRepository(City::class)->findOneBy(['id' => $data['city']]);
		if (!$loginRoleEntity instanceof LoginRole) {
			throw new Exception('Provider login role was not found.');
		}
		if (!$city instanceof City) {
			throw new Exception('Provider city was not found.');
		}

		$provider = new Provider(
			companyName: $this->stringData($data, 'companyName'),
			contactName: $this->stringData($data, 'contactName'),
			contactSurname: $this->stringData($data, 'contactSurname'),
			contactTitle: $this->stringData($data, 'contactTitle'),
			email: $this->stringData($data, 'email'),
			phoneNumber: $this->stringData($data, 'phoneNumber'),
			ico: $this->stringData($data, 'ico'),
			dic: $this->stringData($data, 'dic'),
			password: Passwords::create()->hash($this->stringData($data, 'password', md5(microtime()))),
			streetNo: $this->stringData($data, 'streetNo'),
			city: $city,
			zipCode: $this->stringData($data, 'zipCode'),
			stateProvider: $stateProvider,
			loginRole: $loginRoleEntity,
			hash: md5(microtime()),
		);

		$this->em->persist($provider);

		foreach ($regionIds as $regionId) {
			$region = $this->em->getRepository(Region::class)->find($regionId);
			if ($region instanceof Region) {
				$providerRegion = new ProviderRegion(
					provider: $provider,
					region: $region
				);
				$this->em->persist($providerRegion);
			}
		}

		foreach ($serviceCategoryIds as $categoryId) {
			$serviceCategory = $this->em->getRepository(CategoryService::class)->find($categoryId);
			if ($serviceCategory instanceof CategoryService) {
				$providerServiceCategory = new ProviderServiceCategory(
					provider: $provider,
					serviceCategory: $serviceCategory
				);
				$this->em->persist($providerServiceCategory);
			}
		}

		$this->em->flush();

		$link = (string) $this->linkGenerator->link('Front:ProviderSign:activateProvider', ['hash' => $provider->getHash()]);
		$this->mailSender->sendActivationEmailProvider($provider->getEmail(), $provider->getFullName(), $link);

		return $provider;
	}

	/**
	 * @param array<string, mixed> $data
	 * @throws Exception
	 */
	private function validateInputs(array $data): void
	{
		$email = $this->stringData($data, 'email');
		if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
			throw new Exception('Invalid email address');
		}

		if (($data['password'] ?? '') !== ($data['password2'] ?? '')) {
			throw new Exception('Passwords do not match');
		}

		if (($data['region'] ?? []) === [] || !is_array($data['region'])) {
			throw new Exception('Provider region is required');
		}

		if (($data['serviceCategory'] ?? []) === [] || !is_array($data['serviceCategory'])) {
			throw new Exception('Provider service category is required');
		}

		$existingProvider = $this->em->getRepository(Provider::class)->findOneBy(['email' => $email]);
		if ($existingProvider instanceof Provider) {
			throw new Exception('User with this email already exists');
		}
	}

	public function activateProvider(string $hash): void
	{
		$provider = $this->em->getRepository(Provider::class)->findOneBy(['hash' => $hash]);
		if (!$provider instanceof Provider) {
			throw new InvalidArgumentException('Provider with hash ' . $hash . ' not found');
		}
		if ($provider->getStateProvider()->getId() === StateProviderRepository::STATE_ACTIVATED) {
			throw new UserAlreadyActiveException('User is already activated');
		}

		$stateActivated = $this->em->getRepository(StateProvider::class)
			->findOneBy(['id' => StateProviderRepository::STATE_ACTIVATED]);
		if (!$stateActivated instanceof StateProvider) {
			throw new InvalidArgumentException('Activated provider state not found');
		}

		$provider->setStateProvider($stateActivated);
		$provider->setDateActivated();
		$this->em->persist($provider);
		$this->em->flush();
	}

	/**
	 * @return array{providerId: int, userId: int, email: string, subscriptionStatus: string}
	 */
	public function registerWithSubscription(ProviderRegistrationData $dto): array
	{
		$provider = $this->createProvider($dto->toProviderData());

		return [
			'providerId' => $provider->getId(),
			'userId' => $provider->getId(),
			'email' => $provider->getEmail(),
			'subscriptionStatus' => 'pending_payment',
		];
	}

	private function resolveInitialProviderState(): StateProvider
	{
		$repository = $this->em->getRepository(StateProvider::class);
		$pendingPayment = $repository->find(StateProviderRepository::STATE_PENDING_PAYMENT);
		if ($pendingPayment instanceof StateProvider) {
			return $pendingPayment;
		}

		$fresh = $repository->find(StateProviderRepository::STATE_FRESH);
		if ($fresh instanceof StateProvider) {
			return $fresh;
		}

		throw new Exception('Initial provider state was not found.');
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function stringData(array $data, string $key, string $default = ''): string
	{
		$value = $data[$key] ?? $default;
		if (!is_scalar($value)) {
			return $default;
		}

		return (string) $value;
	}
}
