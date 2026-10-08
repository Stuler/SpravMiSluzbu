<?php declare(strict_types=1);

namespace App\Domain\Provider;

use App\Domain\CategoryService\CategoryService;
use App\Domain\City\City;
use App\Domain\LoginRole\LoginRole;
use App\Domain\Provider\DTO\ProviderRegistrationData;
use App\Domain\Provider\Exception\DuplicateEmailException;
use App\Domain\Provider\Exception\InvalidActivationTokenException;
use App\Domain\Provider\Exception\RegistrationValidationException;
use App\Domain\ProviderRegion\ProviderRegion;
use App\Domain\ProviderServiceCategory\ProviderServiceCategory;
use App\Domain\Region\Region;
use App\Domain\StateProvider\StateProvider;
use App\Domain\StateProvider\StateProviderRepository;
use App\Model\Database\EntityManagerDecorator;
use App\Model\Exception\Logic\InvalidArgumentException;
use App\Model\Mail\MailSender;
use App\Model\Security\Passwords;
use App\Model\Utils\Emails;
use App\Model\Utils\PhoneNumbers;
use Nette\Application\LinkGenerator;
use Tracy\Debugger;
use Tracy\ILogger;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

readonly class ProviderFacade
{

	private const PASSWORD_MIN_LENGTH = 8;
	private const RESEND_COOLDOWN = '-5 minutes';

	public function __construct(
		private EntityManagerDecorator $em,
		private MailSender             $mailSender,
		private LinkGenerator          $linkGenerator,
		private ProviderRepository     $providers,
	)
	{
	}

	/**
	 * Register a new provider in the `fresh` state.
	 *
	 * The account, its region/service-category relations and the activation token
	 * are created in a single transaction. The activation email is sent afterwards;
	 * a delivery failure does not roll back the account but is reported in the result
	 * so the UI can offer a resend.
	 */
	public function register(ProviderRegistrationData $dto): ProviderRegistrationResult
	{
		$email = Emails::normalize($dto->email);
		$this->validate($dto, $email);

		$loginRole = $this->em->getRepository(LoginRole::class)->findOneBy(['name' => LoginRole::ROLE_PROVIDER]);
		if (!$loginRole instanceof LoginRole) {
			throw new InvalidArgumentException('Provider login role was not found.');
		}

		$stateFresh = $this->em->getRepository(StateProvider::class)->find(StateProviderRepository::STATE_FRESH);
		if (!$stateFresh instanceof StateProvider) {
			throw new InvalidArgumentException('Initial provider state (fresh) was not found.');
		}

		$city = $this->em->getRepository(City::class)->find($dto->getCityIdAsInt());
		if (!$city instanceof City || !$city->getUse()) {
			throw new RegistrationValidationException('Selected city does not exist.');
		}

		$regions = $this->resolveRegions($dto->getRegionIds());
		$categories = $this->resolveCategories($dto->getServiceCategoryIds());
		try {
			new \App\Domain\Provider\ValueObject\ProviderProfile($dto->companyName, $dto->getContactFirstName(),
				$dto->getContactLastName(), '', $dto->phone, $dto->ico, '', $dto->getStreetNo(), $city, $dto->zip);
		} catch (\InvalidArgumentException $e) {
			throw new RegistrationValidationException($e->getMessage(), 0, $e);
		}

		$token = ActivationToken::generate();

		$provider = new Provider(
			companyName: $dto->companyName,
			contactName: $dto->getContactFirstName(),
			contactSurname: $dto->getContactLastName(),
			contactTitle: '',
			email: $email,
			phoneNumber: PhoneNumbers::normalize($dto->phone),
			ico: $dto->ico,
			dic: '',
			password: Passwords::create()->hash($dto->password),
			streetNo: $dto->getStreetNo(),
			city: $city,
			zipCode: $dto->zip,
			stateProvider: $stateFresh,
			loginRole: $loginRole,
		);
		$provider->setActivationToken($token->hash, $token->expiresAt);

		try {
			$this->em->wrapInTransaction(function () use ($provider, $regions, $categories): void {
				$this->em->persist($provider);

				foreach ($regions as $region) {
					$this->em->persist(new ProviderRegion(provider: $provider, region: $region));
				}

				foreach ($categories as $category) {
					$this->em->persist(new ProviderServiceCategory(provider: $provider, serviceCategory: $category));
				}
				// Allocate the result ID even when registration joins an outer operation.
				$this->em->flush();
			});
		} catch (UniqueConstraintViolationException $e) {
			if ($this->providers->emailExists($email)) {
				throw new DuplicateEmailException('A provider with this email already exists.', 0, $e);
			}
			throw $e;
		}

		$mailSent = false;
		$this->em->afterCommit(function () use ($provider, $token, &$mailSent): void {
			$mailSent = $this->sendActivationMail($provider, $token->raw);
		});

		return new ProviderRegistrationResult($provider->getId(), $email, $mailSent);
	}

	/**
	 * Look up a fresh provider by the raw activation token without mutating any state.
	 * Returns null for missing, malformed, expired, consumed, or non-fresh tokens.
	 */
	public function findFreshProviderByRawToken(string $rawToken): ?Provider
	{
		$rawToken = trim($rawToken);
		if ($rawToken === '') {
			return null;
		}

		$hash = ActivationToken::hashRaw($rawToken);
		$provider = $this->providers->findByActivationHash($hash);
		if (!$provider instanceof Provider) {
			return null;
		}

		if ($provider->getDateDeleted() !== null || !$provider->isFresh() || !$provider->hasValidActivationToken($hash, new \DateTime())) {
			return null;
		}

		return $provider;
	}

	/**
	 * Validate the token again inside a transaction and activate the provider:
	 * set state to activated, stamp the activation date and clear the token fields.
	 *
	 * @throws InvalidActivationTokenException
	 */
	public function activate(string $rawToken): Provider
	{
		$hash = ActivationToken::hashRaw(trim($rawToken));

		// Re-read the token and account under a write lock shared with resend.
		$provider = $this->em->wrapInTransaction(function () use ($hash): ?Provider {
			$provider = $this->providers->findByActivationHashForUpdate($hash);
			$now = new \DateTimeImmutable();

			if (!$provider instanceof Provider || $provider->getDateDeleted() !== null || !$provider->isFresh() || !$provider->hasValidActivationToken($hash, $now)) {
				return null;
			}

			$activated = $this->em->getRepository(StateProvider::class)->find(StateProviderRepository::STATE_ACTIVATED);
			if (!$activated instanceof StateProvider) {
				throw new InvalidArgumentException('Activated provider state was not found.');
			}

			$provider->activate($activated, $hash, $now);

			return $provider;
		});

		if (!$provider instanceof Provider) {
			throw new InvalidActivationTokenException('Activation token is invalid, expired, or already used.');
		}

		return $provider;
	}

	/**
	 * Issue a fresh activation token and resend the email.
	 *
	 * Always behaves identically regardless of whether the email exists, the account
	 * state, or the cooldown, to prevent account enumeration. A new token is only
	 * issued for a fresh provider that is past the per-account cooldown.
	 */
	public function resendActivation(string $email): void
	{
		$normalized = Emails::normalize($email);
		if ($normalized === '' || filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
			return;
		}

		$provider = $this->providers->findByEmail($normalized);
		if (!$provider instanceof Provider || $provider->getDateDeleted() !== null || !$provider->isFresh()) {
			return;
		}

		$token = $this->em->wrapInTransaction(function () use ($provider): ?ActivationToken {
			$this->providers->refreshForUpdate($provider);
			$now = new \DateTimeImmutable();
			$sentAt = $provider->getActivationSentAt();
			if ($provider->getDateDeleted() !== null || !$provider->isFresh()
				|| ($sentAt !== null && $sentAt > $now->modify(self::RESEND_COOLDOWN))) { return null; }
			$token = ActivationToken::generate($now);
			$provider->setActivationToken($token->hash, $token->expiresAt, $now);
			return $token;
		});

		if ($token !== null) {
			$this->em->afterCommit(function () use ($provider, $token): void { $this->sendActivationMail($provider, $token->raw); });
		}
	}

	public function getProvider(int $id): ?Provider
	{
		$provider = $this->providers->find($id);

		return $provider instanceof Provider ? $provider : null;
	}

	/**
	 * @return Region[]
	 */
	public function getProviderRegions(Provider $provider): array
	{
		$relations = $this->em->getRepository(ProviderRegion::class)->findBy(['provider' => $provider, 'dateDeleted' => null]);

		return array_values(array_map(
			static fn(ProviderRegion $relation): Region => $relation->getRegion(),
			$relations
		));
	}

	/**
	 * @return CategoryService[]
	 */
	public function getProviderServiceCategories(Provider $provider): array
	{
		$relations = $this->em->getRepository(ProviderServiceCategory::class)->findBy(['provider' => $provider, 'dateDeleted' => null]);

		return array_values(array_map(
			static fn(ProviderServiceCategory $relation): CategoryService => $relation->getCategoryService(),
			$relations
		));
	}

	/**
	 * @throws DuplicateEmailException
	 * @throws RegistrationValidationException
	 */
	private function validate(ProviderRegistrationData $dto, string $email): void
	{
		if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
			throw new RegistrationValidationException('A valid email address is required.');
		}

		if (mb_strlen($dto->password) < self::PASSWORD_MIN_LENGTH) {
			throw new RegistrationValidationException('Password must be at least 8 characters long.');
		}

		if ($dto->password !== $dto->confirmPassword) {
			throw new RegistrationValidationException('Passwords do not match.');
		}

		if (trim($dto->firstName) === '' || trim($dto->lastName) === '') {
			throw new RegistrationValidationException('First and last name are required.');
		}

		if (!PhoneNumbers::isValid($dto->phone)) {
			throw new RegistrationValidationException('A valid Slovak mobile or landline number is required.');
		}

		if (trim($dto->companyName) === '') {
			throw new RegistrationValidationException('Company name is required.');
		}

		if (trim($dto->street) === '' || trim($dto->streetNumber) === '') {
			throw new RegistrationValidationException('Street and street number are required.');
		}

		if (trim($dto->zip) === '') {
			throw new RegistrationValidationException('Postcode is required.');
		}

		if ((int) $dto->cityId <= 0) {
			throw new RegistrationValidationException('City is required.');
		}

		if ($dto->getServiceCategoryIds() === []) {
			throw new RegistrationValidationException('At least one service category is required.');
		}

		if ($dto->getRegionIds() === []) {
			throw new RegistrationValidationException('At least one region is required.');
		}

		$existing = $this->providers->findByEmail($email);
		if ($existing instanceof Provider) {
			throw new DuplicateEmailException('A provider with this email already exists.');
		}
	}

	/**
	 * @param int[] $regionIds
	 * @return Region[]
	 */
	private function resolveRegions(array $regionIds): array
	{
		$regions = [];
		foreach ($regionIds as $regionId) {
			$region = $this->em->getRepository(Region::class)->find($regionId);
			if (!$region instanceof Region || !$region->getUse()) {
				throw new RegistrationValidationException('Selected region does not exist.');
			}
			$regions[] = $region;
		}

		return $regions;
	}

	/**
	 * @param int[] $categoryIds
	 * @return CategoryService[]
	 */
	private function resolveCategories(array $categoryIds): array
	{
		$categories = [];
		foreach ($categoryIds as $categoryId) {
			$category = $this->em->getRepository(CategoryService::class)->find($categoryId);
			if (!$category instanceof CategoryService || !$category->isSelectable()) {
				throw new RegistrationValidationException('Selected service category does not exist.');
			}
			$categories[] = $category;
		}

		return $categories;
	}

	/**
	 * Send the activation email. Never persists or logs the raw token: on failure only
	 * a sanitized message is logged so the link (which carries the token) cannot leak.
	 */
	private function sendActivationMail(Provider $provider, string $rawToken): bool
	{
		try {
			$link = (string) $this->linkGenerator->link('Front:ProviderSign:activate', ['token' => $rawToken]);
			$this->mailSender->sendActivationEmailProvider($provider->getEmail(), $provider->getFullName(), $link);

			return true;
		} catch (\Throwable $e) {
			Debugger::log(
				sprintf('Failed to confirm provider activation email delivery for provider #%d.', $provider->getId()),
				ILogger::ERROR
			);

			return false;
		}
	}
}
