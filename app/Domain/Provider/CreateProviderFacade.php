<?php declare(strict_types=1);

namespace App\Domain\Provider;

use App\Domain\CategoryService\CategoryService;
use App\Domain\City\City;
use App\Domain\LoginRole\LoginRole;
use App\Domain\ProviderRegion\ProviderRegion;
use App\Domain\ProviderServiceCategory\ProviderServiceCategory;
use App\Domain\Region\Region;
use App\Domain\StateProvider\StateProvider;
use App\Domain\StateUser\StateUserRepository;
use App\Model\Database\EntityManagerDecorator;
use App\Model\Mail\MailSender;
use App\Model\Security\Passwords;
use Exception;

readonly class CreateProviderFacade
{


	public function __construct(
		private EntityManagerDecorator $em,
		private MailSender             $mailSender,
	)
	{
	}

	/**
	 * @param array<string, scalar> $data
	 * @throws Exception
	 */
	public function createProvider(array $data): Provider
	{
		$this->validateInputs($data);

		$loginRole = $data['role'] ?? LoginRole::ROLE_PROVIDER;
		$loginRoleEntity = $this->em->getRepository(LoginRole::class)->findOneBy(['name' => $loginRole]);
		$stateProvider = $this->em->getRepository(StateProvider::class)->findOneBy(['id' => StateUserRepository::STATE_FRESH]);
		$city = $this->em->getRepository(City::class)->findOneBy(['id' => $data['city']]);

		$provider = new Provider(
			companyName: (string)$data['companyName'],
			contactName: (string)$data['contactName'],
			contactSurname: (string)$data['contactSurname'],
			contactTitle: (string)$data['contactTitle'],
			email: (string)$data['email'],
			phoneNumber: (string)$data['phoneNumber'],
			ico: (string)$data['ico'],
			dic: (string)$data['dic'],
			password: Passwords::create()->hash(strval($data['password'] ?? md5(microtime()))),
			streetNo: (string)$data['streetNo'],
			city: $city,
			zipCode: (string)$data['zipCode'],
			stateProvider: $stateProvider,
			loginRole: $loginRoleEntity
		);
		$provider->activate();

		$this->em->persist($provider);

		foreach ($data['region'] as $regionId) {
			$region = $this->em->getRepository(Region::class)->find($regionId);
			if ($region) {
				$providerRegion = new ProviderRegion(
					provider: $provider,
					region: $region
				);
				$this->em->persist($providerRegion);
			}
		}

		foreach ($data['serviceCategory'] as $categoryId) {
			$serviceCategory = $this->em->getRepository(CategoryService::class)->find($categoryId);
			if ($serviceCategory) {
				$providerServiceCategory = new ProviderServiceCategory(
					provider: $provider,
					serviceCategory: $serviceCategory
				);
				$this->em->persist($providerServiceCategory);
			}
		}

		$this->em->flush();

		$this->mailSender->sendActivationEmail($provider->getEmail(), $provider->getFullName());

		return $provider;
	}

	/**
	 * @throws Exception
	 */
	private function validateInputs(array $data): void
	{
		if ($data['password'] !== $data['password2']) {
			throw new Exception('Passwords do not match');
		}

		$existingProvider = $this->em->getRepository(Provider::class)->findOneBy(['email' => $data['email']]);
		if ($existingProvider) {
			throw new Exception('User with this email already exists');
		}
	}

}
