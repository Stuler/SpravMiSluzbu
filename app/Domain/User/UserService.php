<?php declare(strict_types=1);

namespace App\Domain\User;

use App\Model\Database\EntityManagerDecorator;
use App\Model\Security\SecurityUser;

readonly class UserService
{
	public function __construct(
		private EntityManagerDecorator $em,
		private SecurityUser           $user,
	)
	{
	}

	public function getUserEntity(): ?User
	{
		$identity = $this->user->getIdentity();
		return $this->em->getRepository(User::class)->find($identity->getId());
	}
}
