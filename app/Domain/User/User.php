<?php declare(strict_types=1);

namespace App\Domain\User;

use App\Domain\LoginRole\LoginRole;
use App\Model\Database\Entity\TCreatedBy;
use App\Model\Database\Entity\TDateCreated;
use App\Model\Database\Entity\TDateDeleted;
use App\Model\Database\Entity\TDateModified;
use App\Model\Database\Entity\TDeletedBy;
use App\Model\Database\Entity\TId;
use App\Model\Exception\Logic\InvalidArgumentException;
use App\Model\Security\Identity;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="user")
 */
class User
{
	use TId, TDateCreated, TDateModified, TDateDeleted, TCreatedBy, TDeletedBy;

	public const ROLE_ADMIN = 'admin';
	public const ROLE_MEMBER = 'member';
	public const ROLE_GUEST = 'guest';
	public const ROLE_PREMIUM = 'premium';
	public const ROLE_PROVIDER = 'provider';
	public const ROLE_PRO = 'pro';

	public const STATE_FRESH = 1;
	public const STATE_ACTIVATED = 2;
	public const STATE_BLOCKED = 3;

	public const STATES = [self::STATE_FRESH, self::STATE_BLOCKED, self::STATE_ACTIVATED];

	/** @ORM\Column(type="string", length=255, nullable=FALSE, unique=false) */
	private string $name;

	/** @ORM\Column(type="string", length=255, nullable=FALSE, unique=false) */
	private string $surname;


	/**
	 * @ORM\Column(type="string", length=100)
	 */
	private string $username;

	/**
	 * @ORM\Column(type="string", length=100)
	 */
	private string $email;

	/** @ORM\Column(type="integer", length=10, nullable=FALSE) */
	private int $state;

	/**
	 * @ORM\Column(type="string", length=100, nullable=true)
	 */
	private ?string $password = null;

	/**
	 * @ORM\ManyToOne(targetEntity="App\Domain\LoginRole\LoginRole")
	 * @ORM\JoinColumn(name="login_role_id", referencedColumnName="id")
	 */
	private LoginRole $loginRole;

	/**
	 * @ORM\Column(type="datetime", options={"default": "CURRENT_TIMESTAMP"}, nullable=true)
	 */
	private \DateTime $dateLastLogin;

	/** @ORM\Column(type="string", length=255, nullable=FALSE) */
	private string $role;

	/**
	 * @ORM\Column(type="text", nullable=true)
	 */
	private ?string $note = null;

	public function __construct(
		string $name,
		string $surname,
		string $email,
		string $username,
		string $passwordHash,
		LoginRole $loginRole

	)
	{
		$this->name = $name;
		$this->surname = $surname;
		$this->email = $email;
		$this->username = $username;
		$this->password = $passwordHash;
		$this->dateCreated = new \DateTime();
		$this->loginRole = $loginRole;
	}

	// Getters and setters...
	public function getUsername(): string
	{
		return $this->nick;
	}

	public function setUsername(string $nick): void
	{
		$this->nick = $nick;
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
		$this->state = self::STATE_ACTIVATED;
	}

	public function setState(int $state): void
	{
		if (!in_array($state, self::STATES, true)) {
			throw new InvalidArgumentException(sprintf('Unsupported state %s', $state));
		}

		$this->state = $state;
	}

	public function isActivated(): bool
	{
		return $this->state === self::STATE_ACTIVATED;
	}

	public function toIdentity(): Identity
	{
		return new Identity($this->getId(), [$this->loginRole->getName()], [
			'email' => $this->email,
			'name' => $this->name,
			'surname' => $this->surname,
			'state' => $this->state,
			'gravatar' => $this->getGravatar(),
		]);
	}

	public function getGravatar(): string
	{
		return 'https://www.gravatar.com/avatar/' . md5($this->email);
	}

}
