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

	public function isCustomer(): bool
	{
		return $this->isLoggedIn() && $this->getIdentity()->getAccountType() === Identity::ACCOUNT_TYPE_CUSTOMER
			&& $this->isInRole(LoginRole::ROLE_MEMBER);
	}

	/**
	 * Whether the current session is an authenticated provider account.
	 * Requires the provider role and a provider-scoped identity (accountType=provider).
	 */
	public function isProvider(): bool
	{
		if (!$this->isLoggedIn()) {
			return false;
		}

		return $this->getIdentity()->isProvider()
			&& $this->isInRole(LoginRole::ROLE_PROVIDER);
	}

}
