<?php declare(strict_types = 1);

namespace Database\Fixtures;

use App\Domain\User\User;
use App\Model\Security\Passwords;
use Doctrine\Persistence\ObjectManager;

class UserFixture extends AbstractFixture
{

	public function getOrder(): int
	{
		return 1;
	}

	public function load(ObjectManager $manager): void
	{
		foreach ($this->getUsers() as $user) {
			$loginRole = $this->getReference(LoginRoleFixture::ADMIN_ROLE);

			$entity = new User(
				$user['name'],
				$user['surname'],
				$user['email'],
				$user['username'],
				$this->container->getByType(Passwords::class)->hash($user['password']),
				$loginRole
			);
			$entity->activate();
			$entity->setLoginRole($loginRole);

			$manager->persist($entity);
		}
		$manager->flush();
	}

	/**
	 * @return mixed[]
	 */
	protected function getUsers(): iterable
	{
		yield [
			'email' => 'admin@admin.cz',
			'name' => 'padmin',
			'surname' => 'Admin',
			'username' => 'adminpj',
			'role' => User::ROLE_ADMIN,
			'password' => 'password',
			'loginRole' => 1,
		];
	}

}
