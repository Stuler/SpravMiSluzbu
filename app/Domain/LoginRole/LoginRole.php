<?php declare(strict_types=1);

namespace App\Domain\LoginRole;

use App\Model\Database\Entity\TId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'login_role')]
class LoginRole
{
	use TId;

	public const ROLE_MEMBER = 'member';
	public const ROLE_PRO = 'pro';
	public const ROLE_PROVIDER = 'provider';
	public const ROLE_GUEST = 'guest';
	public const ROLE_PREMIUM = 'premium';
	public const ROLE_ADMIN = 'admin';

	#[ORM\Column(type: 'string', length: 255, unique: true, nullable: false)]
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
