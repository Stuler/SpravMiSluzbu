<?php declare(strict_types=1);

namespace App\Domain\LogAccess;

use App\Domain\User\User;
use App\Model\Database\Entity\TDateCreated;
use App\Model\Database\Entity\TId;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="log_access")
 */
class LogAccess
{
	use TId, TDateCreated;

	/**
	 * @ORM\ManyToOne(targetEntity="App\Domain\User\User")
	 * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
	 */
	private User $user;

	/**
	 * @ORM\Column(type="string", length=32, options={"default": ""})
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

	public function getIpAddress(): string
	{
		return $this->ipAddress;
	}

	public function setIpAddress(string $ipAddress): void
	{
		$this->ipAddress = $ipAddress;
	}
}
