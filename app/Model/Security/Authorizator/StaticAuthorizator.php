<?php declare(strict_types = 1);

namespace App\Model\Security\Authorizator;

use App\Domain\User\User;
use Nette\Security\Permission;

final class StaticAuthorizator extends Permission
{

	/**
	 * Create ACL
	 */
	public function __construct()
	{
		$this->addRoles();
		$this->addResources();
		$this->addPermissions();
	}

	/**
	 * Setup roles
	 */
	protected function addRoles(): void
	{
		$this->addRole(User::ROLE_GUEST);
		$this->addRole(User::ROLE_MEMBER, User::ROLE_GUEST);
		$this->addRole(User::ROLE_PREMIUM, User::ROLE_MEMBER);
		$this->addRole(User::ROLE_PROVIDER, User::ROLE_MEMBER);
		$this->addRole(User::ROLE_PRO, User::ROLE_PROVIDER);
		$this->addRole(User::ROLE_ADMIN, [User::ROLE_GUEST, User::ROLE_MEMBER, User::ROLE_PREMIUM, User::ROLE_PROVIDER, User::ROLE_PRO]);
	}

	/**
	 * Setup resources
	 */
	protected function addResources(): void
	{
		$this->addResource('Admin:Home');
	}

	/**
	 * Setup ACL
	 */
	protected function addPermissions(): void
	{
		$this->allow(User::ROLE_ADMIN, [
			'Admin:Home',
		]);
	}

}
