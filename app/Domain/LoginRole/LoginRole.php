<?php declare(strict_types=1);

namespace App\Domain\LoginRole;

use App\Model\Database\Entity\TId;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="login_role")
 */
class LoginRole
{
	use TId;

	/**
	 * @ORM\Column(type="string", length=64)
	 */
	private string $name;

	// Getters and setters...
	public function getName(): string
	{
		return $this->name;
	}

	public function setName(string $name): void
	{
		$this->name = $name;
	}
}
