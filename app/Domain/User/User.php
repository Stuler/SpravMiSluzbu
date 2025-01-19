<?php declare(strict_types=1);

namespace App\Domain\User;

use App\Domain\LoginRole\LoginRole;
use App\Domain\StateUser\StateUser;
use App\Domain\StateUser\StateUserRepository;
use App\Model\Database\Entity\TCreatedBy;
use App\Model\Database\Entity\TDateCreated;
use App\Model\Database\Entity\TDateDeleted;
use App\Model\Database\Entity\TDateModified;
use App\Model\Database\Entity\TDeletedBy;
use App\Model\Database\Entity\TId;
use App\Model\Exception\Logic\InvalidArgumentException;
use App\Model\Security\Identity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'user')]
class User
{
	use TId, TDateCreated, TDateModified, TDateDeleted, TCreatedBy, TDeletedBy;

	public function __construct(
		#[ORM\Column(type: 'string', length: 255, nullable: false)]
		private string    $name,

		#[ORM\Column(type: 'string', length: 255, nullable: false)]
		private string    $surname,

		#[ORM\Column(type: 'string', length: 100, nullable: false)]
		private string    $email,

		#[ORM\Column(type: 'string', length: 100, nullable: false)]
		private string    $password,

		#[ORM\ManyToOne(targetEntity: LoginRole::class)]
		#[ORM\JoinColumn(name: 'login_role_id', referencedColumnName: 'id')]
		private LoginRole $loginRole,

		#[ORM\ManyToOne(targetEntity: StateUser::class)]
		#[ORM\JoinColumn(name: 'state_user_id', referencedColumnName: 'id')]
		private StateUser $stateUser,

		#[ORM\Column(type: 'string', length: 100, nullable: false)]
		private string    $streetNo,

		#[ORM\Column(type: 'string', length: 100, nullable: false)]
		private string    $city,

		#[ORM\Column(type: 'string', length: 100, nullable: false)]
		private string    $zipCode,

		#[ORM\Column(type: 'datetime', nullable: true, options: ['default' => 'CURRENT_TIMESTAMP'])]
		private \DateTime $dateLastLogin = new \DateTime(),

		#[ORM\Column(type: 'text', nullable: true)]
		private ?string   $note = null
	)
	{
		$this->dateCreated = new \DateTime();
	}

	// Getters and setters...
	public function getName(): string
	{
		return $this->name;
	}

	public function getSurname(): string
	{
		return $this->surname;
	}

	public function getFullName(): string
	{
		return $this->name . ' ' . $this->surname;
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

	public function getLoginRole(): LoginRole
	{
		return $this->loginRole;
	}

	public function setLoginRole(LoginRole $loginRole): void
	{
		$this->loginRole = $loginRole;
	}

	public function getStateUser(): StateUser
	{
		return $this->stateUser;
	}

	public function setStateUser(StateUser $stateUser): void
	{
		$this->stateUser = $stateUser;
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

	public function activate(): void
	{
		// set stateUser to activated
		$this->stateUser->setId(StateUserRepository::STATE_ACTIVATED);
	}

	public function setState(StateUser $stateUser): void
	{
		if (!in_array($stateUser->getId(), StateUserRepository::STATES, true)) {
			throw new InvalidArgumentException(sprintf('Unsupported state %s', $stateUser->getId()));
		}

		$this->stateUser = $stateUser;
	}

	public function isActivated(): bool
	{
		return $this->stateUser->getId() === StateUserRepository::STATE_ACTIVATED;
	}

	public function toIdentity(): Identity
	{
		return new Identity($this->getId(), [$this->loginRole->getName()], [
			'email' => $this->email,
			'name' => $this->name,
			'surname' => $this->surname,
			'state' => $this->stateUser->getId(),
			'gravatar' => $this->getGravatar(),
		]);
	}

	public function getGravatar(): string
	{
		return 'https://www.gravatar.com/avatar/' . md5($this->email);
	}
}
