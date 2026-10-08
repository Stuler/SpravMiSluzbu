<?php declare(strict_types = 1);

namespace App\Model\Security;

use Nette\Security\SimpleIdentity as NetteIdentity;

class Identity extends NetteIdentity
{

	public const ACCOUNT_TYPE_PROVIDER = 'provider';
	public const ACCOUNT_TYPE_CUSTOMER = 'customer';

	public function getFullname(): string
	{
		return sprintf('%s %s', $this->data['name'] ?? '', $this->data['surname'] ?? '');
	}

	public function getAccountType(): ?string
	{
		$type = $this->data['accountType'] ?? null;

		return is_string($type) ? $type : null;
	}

	public function isProvider(): bool
	{
		return $this->getAccountType() === self::ACCOUNT_TYPE_PROVIDER;
	}

}
