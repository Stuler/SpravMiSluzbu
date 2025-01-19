<?php declare(strict_types=1);

namespace App\Model\Security\Authorizator;

use App\Domain\LoginRole\LoginRole;
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
		$this->addRole(LoginRole::ROLE_GUEST);
		$this->addRole(LoginRole::ROLE_MEMBER, LoginRole::ROLE_GUEST);
		$this->addRole(LoginRole::ROLE_PREMIUM, LoginRole::ROLE_MEMBER);
		$this->addRole(LoginRole::ROLE_PROVIDER, LoginRole::ROLE_MEMBER);
		$this->addRole(LoginRole::ROLE_PRO, LoginRole::ROLE_PROVIDER);
		$this->addRole(LoginRole::ROLE_ADMIN, [LoginRole::ROLE_GUEST, LoginRole::ROLE_MEMBER, LoginRole::ROLE_PREMIUM, LoginRole::ROLE_PROVIDER, LoginRole::ROLE_PRO]);
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
		$this->allow(LoginRole::ROLE_ADMIN, [
			'Admin:Home',
		]);
	}

}
