<?php declare(strict_types=1);

namespace App\Model\Security;

use App\Domain\LoginRole\LoginRole;
use Nette\Security\User as NetteUser;

/**
 * @method Identity getIdentity()
 */
final class SecurityUser extends NetteUser
{

	public function isAdmin(): bool
	{
		return $this->isInRole(LoginRole::ROLE_ADMIN);
	}

}
