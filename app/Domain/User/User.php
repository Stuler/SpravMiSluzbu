<?php declare(strict_types=1);

namespace App\Domain\User;

use App\Domain\LoginRole\LoginRole;
use App\Model\Database\Entity\TCreatedBy;
use App\Model\Database\Entity\TDateCreated;
use App\Model\Database\Entity\TDateDeleted;
use App\Model\Database\Entity\TDateModified;
use App\Model\Database\Entity\TDeletedBy;
use App\Model\Database\Entity\TId;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="user")
 */
class User
{
	use TId, TDateCreated, TDateModified, TDateDeleted, TCreatedBy, TDeletedBy;

	public const ROLE_ADMIN = 'admin';
	public const ROLE_USER = 'user';

	public const STATE_FRESH = 1;
	public const STATE_ACTIVATED = 2;
	public const STATE_BLOCKED = 3;

	public const STATES = [self::STATE_FRESH, self::STATE_BLOCKED, self::STATE_ACTIVATED];

	/**
	 * @ORM\Column(type="string", length=100)
	 */
	private string $nick;

	/**
	 * @ORM\Column(type="string", length=100)
	 */
	private string $email;

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
	 * @ORM\Column(type="datetime", options={"default": "CURRENT_TIMESTAMP"})
	 */
	private \DateTime $dateLastLogin;

	/**
	 * @ORM\Column(type="text", nullable=true)
	 */
	private ?string $note = null;

	// Getters and setters...
	public function getNick(): string
	{
		return $this->nick;
	}

	public function setNick(string $nick): void
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

	public function getPassword(): ?string
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

	public function setDateLastLogin(\DateTime $dateLastLogin): void
	{
		$this->dateLastLogin = $dateLastLogin;
	}

	public function getNote(): ?string
	{
		return $this->note;
	}

	public function setNote(?string $note): void
	{
		$this->note = $note;
	}
}
