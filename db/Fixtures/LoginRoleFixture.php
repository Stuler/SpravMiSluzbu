<?php declare(strict_types=1);

namespace Database\Fixtures;

use App\Domain\LoginRole\LoginRole;
use Doctrine\Persistence\ObjectManager;

class LoginRoleFixture extends AbstractFixture
{
	public const ADMIN_ROLE = 'admin-role';
	public const MEMBER_ROLE = 'member-role';
	public const GUEST_ROLE = 'guest-role';
	public const PROVIDER_ROLE = 'provider-role';

	public function load(ObjectManager $manager): void
	{
		$roles = [
			'admin' => self::ADMIN_ROLE,
			'member' => self::MEMBER_ROLE,
			'guest' => self::GUEST_ROLE,
			'provider' => self::PROVIDER_ROLE
		];

		foreach ($roles as $roleName => $reference) {
			$loginRole = new LoginRole();
			$loginRole->setName($roleName);
			$manager->persist($loginRole);
			$this->addReference($reference, $loginRole);
		}

		$manager->flush();
	}
}
