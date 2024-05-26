<?php declare(strict_types=1);

namespace App\Domain\LoginPermanent;

use App\Domain\User\User;
use App\Model\Database\Entity\TDateCreated;
use App\Model\Database\Entity\TId;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="login_permanent")
 */
class LoginPermanent
{
	use TId, TDateCreated;

	/**
	 * @ORM\ManyToOne(targetEntity="App\Domain\User\User")
	 * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
	 */
	private User $user;

	/**
	 * @ORM\Column(type="string", length=64, options={"default": ""})
	 */
	private string $token;

	/**
	 * @ORM\Column(type="datetime")
	 */
	private \DateTime $lastLogin;

	// Getters and setters...
	public function getUser(): User
	{
		return $this->user;
	}

	public function setUser(User $user): void
	{
		$this->user = $user;
	}

	public function getToken(): string
	{
		return $this->token;
	}

	public function setToken(string $token): void
	{
		$this->token = $token;
	}

	public function getLastLogin(): \DateTime
	{
		return $this->lastLogin;
	}

	public function setLastLogin(\DateTime $lastLogin): void
	{
		$this->lastLogin = $lastLogin;
	}
}
