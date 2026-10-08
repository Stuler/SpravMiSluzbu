<?php declare(strict_types=1);

namespace App\Domain\Provider;

use App\Domain\City\City;
use App\Domain\LoginRole\LoginRole;
use App\Domain\StateProvider\StateProvider;
use App\Domain\StateProvider\StateProviderRepository;
use App\Model\Database\Entity\TCreatedBy;
use App\Model\Database\Entity\TDateCreated;
use App\Model\Database\Entity\TDateDeleted;
use App\Model\Database\Entity\TDateModified;
use App\Model\Database\Entity\TDeletedBy;
use App\Model\Database\Entity\TId;
use App\Model\Security\Identity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'provider')]
class Provider
{
	use TId, TDateCreated, TDateModified, TDateDeleted, TCreatedBy, TDeletedBy;

	#[ORM\Column(type: 'boolean', options: ['default' => true])]
	private bool $acceptsDemands = true;
	#[ORM\Column(type: 'boolean', options: ['default' => false])]
	private bool $administrativelyBlocked = false;
	#[ORM\Column(type: 'integer', options: ['default' => 1])]
	private int $sessionVersion = 1;

	public function setAdministrativelyBlocked(bool $blocked): void { $this->administrativelyBlocked = $blocked; $this->sessionVersion++; }
	public function getSessionVersion(): int { return $this->sessionVersion; }
	public function updateProfile(string $companyName, string $contactName, string $surname, string $title, string $phone, string $ico, string $dic, string $streetNo, City $city, string $zipCode): void
	{
		$this->companyName = $companyName; $this->contactName = $contactName; $this->contactSurname = $surname;
		$this->contactTitle = $title; $this->phoneNumber = $phone; $this->ico = $ico; $this->dic = $dic;
		$this->streetNo = $streetNo; $this->city = $city; $this->zipCode = $zipCode;
	}
	#[ORM\Column(type: 'text', nullable: true)]
	private ?string $serviceDescription = null;

	public function acceptsDemands(): bool { return $this->acceptsDemands; }
	public function setAcceptsDemands(bool $accepts): void { $this->acceptsDemands = $accepts; }
	public function getServiceDescription(): ?string { return $this->serviceDescription; }
	public function setServiceDescription(?string $description): void { $this->serviceDescription = $description; }

	#[ORM\Column(name: 'activation_token_hash', type: 'string', length: 64, nullable: true)]
	private ?string $activationTokenHash = null;

	#[ORM\Column(name: 'activation_token_expires_at', type: 'datetime', nullable: true)]
	private ?\DateTime $activationTokenExpiresAt = null;

	#[ORM\Column(name: 'activation_sent_at', type: 'datetime', nullable: true)]
	private ?\DateTime $activationSentAt = null;

	public function __construct(
		#[ORM\Column(type: 'string', length: 255, nullable: false)]
		private string        $companyName,

		#[ORM\Column(type: 'string', length: 64, nullable: false)]
		private string        $contactName,

		#[ORM\Column(type: 'string', length: 64, nullable: false)]
		private string        $contactSurname,

		#[ORM\Column(type: 'string', length: 32, nullable: false)]
		private string        $contactTitle,

		#[ORM\Column(type: 'string', length: 100, unique: true)]
		private string        $email,

		#[ORM\Column(type: 'string', length: 100)]
		private string        $phoneNumber,

		#[ORM\Column(type: 'string', length: 100)]
		private string        $ico,

		#[ORM\Column(type: 'string', length: 100)]
		private string        $dic,

		#[ORM\Column(type: 'string', length: 100, nullable: false)]
		private string        $password,

		#[ORM\Column(type: 'string', length: 100, nullable: false)]
		private string        $streetNo,

		#[ORM\ManyToOne(targetEntity: City::class)]
		#[ORM\JoinColumn(name: 'city_id', referencedColumnName: 'id')]
		private City          $city,

		#[ORM\Column(type: 'string', length: 100, nullable: false)]
		private string        $zipCode,

		#[ORM\ManyToOne(targetEntity: StateProvider::class)]
		#[ORM\JoinColumn(name: 'state_provider_id', referencedColumnName: 'id')]
		private StateProvider $stateProvider,

		#[ORM\ManyToOne(targetEntity: LoginRole::class)]
		#[ORM\JoinColumn(name: 'login_role_id', referencedColumnName: 'id')]
		private LoginRole     $loginRole,

		#[ORM\Column(type: 'datetime', nullable: true)]
		private ?\DateTime    $dateActivated = null,

		#[ORM\Column(type: 'text', nullable: true)]
		private ?string       $note = null,

		#[ORM\Column(type: 'datetime', nullable: true, options: ['default' => 'CURRENT_TIMESTAMP'])]
		private \DateTime     $dateLastLogin = new \DateTime()
	)
	{
		$this->dateCreated = new \DateTime();
	}

	public function getCompanyName(): string
	{
		return $this->companyName;
	}

	public function getContactName(): string
	{
		return $this->contactName;
	}

	public function getSurname(): string
	{
		return $this->contactSurname;
	}

	public function getContactTitle(): string
	{
		return $this->contactTitle;
	}

	public function getFullName(): string
	{
		return trim($this->contactName . ' ' . $this->contactSurname);
	}

	public function getEmail(): string
	{
		return $this->email;
	}

	public function setEmail(string $email): void
	{
		$this->email = $email;
	}

	public function getPhoneNumber(): string
	{
		return $this->phoneNumber;
	}

	public function getIco(): string
	{
		return $this->ico;
	}

	public function getDic(): string
	{
		return $this->dic;
	}

	public function getStreetNo(): string
	{
		return $this->streetNo;
	}

	public function getCity(): City
	{
		return $this->city;
	}

	public function getZipCode(): string
	{
		return $this->zipCode;
	}

	public function getPasswordHash(): ?string
	{
		return $this->password;
	}

	public function getDateLastLogin(): \DateTime
	{
		return $this->dateLastLogin;
	}

	public function setDateLastLogin(): void
	{
		$this->dateLastLogin = new \DateTime();
	}

	public function getNote(): ?string
	{
		return $this->note;
	}

	public function setNote(?string $note): void
	{
		$this->note = $note;
	}

	public function getDateActivated(): ?\DateTime
	{
		return $this->dateActivated;
	}

	public function setDateActivated(): void
	{
		$this->dateActivated = new \DateTime();
	}

	// === Activation token ===================================================

	/**
	 * Store the hash of a freshly issued activation token. Any previous token is
	 * invalidated. The raw token is never stored here.
	 */
	public function setActivationToken(string $tokenHash, \DateTime $expiresAt): void
	{
		$this->activationTokenHash = $tokenHash;
		$this->activationTokenExpiresAt = $expiresAt;
		$this->activationSentAt = new \DateTime();
	}

	public function clearActivationToken(): void
	{
		$this->activationTokenHash = null;
		$this->activationTokenExpiresAt = null;
	}

	public function getActivationTokenHash(): ?string
	{
		return $this->activationTokenHash;
	}

	public function getActivationTokenExpiresAt(): ?\DateTime
	{
		return $this->activationTokenExpiresAt;
	}

	public function getActivationSentAt(): ?\DateTime
	{
		return $this->activationSentAt;
	}

	/**
	 * Whether the currently stored activation token matches the given hash and is not expired.
	 */
	public function hasValidActivationToken(string $tokenHash, \DateTime $now): bool
	{
		return $this->activationTokenHash !== null
			&& hash_equals($this->activationTokenHash, $tokenHash)
			&& $this->activationTokenExpiresAt !== null
			&& $this->activationTokenExpiresAt > $now;
	}

	// === State ==============================================================

	public function setStateProvider(StateProvider $state): void
	{
		$this->stateProvider = $state;
	}

	public function getStateProvider(): StateProvider
	{
		return $this->stateProvider;
	}

	public function getStateId(): int
	{
		return $this->stateProvider->getId();
	}

	public function isActivated(): bool
	{
		return !$this->isBlocked() && $this->getStateId() === StateProviderRepository::STATE_ACTIVATED;
	}

	public function isFresh(): bool
	{
		return !$this->isBlocked() && $this->getStateId() === StateProviderRepository::STATE_FRESH;
	}

	public function isBlocked(): bool
	{
		return $this->administrativelyBlocked || $this->getStateId() === StateProviderRepository::STATE_BLOCKED;
	}

	public function getLoginRole(): LoginRole
	{
		return $this->loginRole;
	}

	/**
	 * Build a Nette identity scoped to a provider account.
	 */
	public function toIdentity(): Identity
	{
		return new Identity($this->getId(), [LoginRole::ROLE_PROVIDER], [
			'email' => $this->email,
			'name' => $this->companyName,
			'surname' => $this->contactSurname,
			'accountType' => Identity::ACCOUNT_TYPE_PROVIDER,
			'providerSessionVersion' => $this->sessionVersion,
			'state' => $this->getStateId(),
			'gravatar' => $this->getGravatar(),
		]);
	}

	public function getGravatar(): string
	{
		return 'https://www.gravatar.com/avatar/' . md5($this->email);
	}
}
