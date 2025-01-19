<?php declare(strict_types=1);

namespace App\Domain\StateUser;

use App\Model\Database\Repository\AbstractRepository;

/**
 * @method StateUser|NULL find($id, ?int $lockMode = NULL, ?int $lockVersion = NULL)
 * @method StateUser|NULL findOneBy(array $criteria, array $orderBy = NULL)
 * @method StateUser[] findAll()
 * @method StateUser[] findBy(array $criteria, array $orderBy = NULL, ?int $limit = NULL, ?int $offset = NULL)
 * @extends AbstractRepository<StateUser>
 */
class StateUserRepository extends AbstractRepository
{

	public const STATES = [StateUserRepository::STATE_FRESH, StateUserRepository::STATE_BLOCKED, StateUserRepository::STATE_ACTIVATED];
	public const STATE_ACTIVATED = 2;
	public const STATE_BLOCKED = 3;
	public const STATE_FRESH = 1;

	public function findById(int $id): ?StateUser
	{
		return $this->find($id);
	}

}
