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

	public function __construct(
		#[ORM\Column(type: 'string', length: 255, nullable: false)]
		private string        $companyName,

		#[ORM\Column(type: 'string', length: 64, nullable: false)]
		private string        $contactName,

		#[ORM\Column(type: 'string', length: 64, nullable: false)]
		private string        $contactSurname,

		#[ORM\Column(type: 'string', length: 32, nullable: false)]
		private string        $contactTitle,

		#[ORM\Column(type: 'string', length: 100)]
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

		#[ORM\Column(type: 'string', length: 255, nullable: false)]
		private string        $hash,

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

	// Getters and setters...
	public function getCompanyName(): string
	{
		return $this->companyName;
	}

	public function getSurname(): string
	{
		return $this->contactSurname;
	}

	public function getFullName(): string
	{
		return $this->contactName . ' ' . $this->contactSurname;
	}

	public function getEmail(): string
	{
		return $this->email;
	}

	public function setEmail(string $email): void
	{
		$this->email = $email;
	}

	public function getPasswordHash(): ?string
	{
		return $this->password;
	}

	public function setPassword(?string $password): void
	{
		$this->password = $password;
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

	public function getHash(): string
	{
		return $this->hash;
	}

	public function setDateActivated(): void
	{
		$this->dateActivated = new \DateTime();
	}

	public function activate(): void
	{
		$this->stateProvider->setId(StateProviderRepository::STATE_ACTIVATED);
	}

	public function setStateProvider(StateProvider $state): void
	{
		$this->stateProvider = $state;
	}

	public function getStateProvider(): StateProvider
	{
		return $this->stateProvider;
	}

	public function isActivated(): bool
	{
		return $this->stateProvider->getId() === StateProviderRepository::STATE_ACTIVATED;
	}

	public function toIdentity(): Identity
	{
		return new Identity($this->getId(), [$this->loginRole->getName()], [
			'email' => $this->email,
			'name' => $this->companyName,
			'surname' => $this->contactSurname,
			'state' => $this->stateProvider,
			'gravatar' => $this->getGravatar(),
		]);
	}

	public function getGravatar(): string
	{
		return 'https://www.gravatar.com/avatar/' . md5($this->email);
	}
}
