<?php declare(strict_types=1);

namespace App\Domain\ForgottenPassword;

use App\Domain\User\User;
use App\Model\Database\Entity\TDateCreated;
use App\Model\Database\Entity\TId;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="forgotten_password")
 */
class ForgottenPassword
{
	use TId, TDateCreated;

	/**
	 * @ORM\ManyToOne(targetEntity="App\Domain\User\User")
	 * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
	 */
	private User $user;

	/**
	 * @ORM\Column(type="string", length=12, options={"default": ""})
	 */
	private string $uid;

	/**
	 * @ORM\Column(type="string", length=20, options={"default": ""})
	 */
	private string $ipAddress;

	// Getters and setters...
	public function getUser(): User
	{
		return $this->user;
	}

	public function setUser(User $user): void
	{
		$this->user = $user;
	}

	public function getUid(): string
	{
		return $this->uid;
	}

	public function setUid(string $uid): void
	{
		$this->uid = $uid;
	}

	public function getIpAddress(): string
	{
		return $this->ipAddress;
	}

	public function setIpAddress(string $ipAddress): void
	{
		$this->ipAddress = $ipAddress;
	}
}
