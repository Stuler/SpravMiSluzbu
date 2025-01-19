<?php declare(strict_types=1);

namespace App\Domain\StateProvider;

use App\Model\Database\Repository\AbstractRepository;

/**
 * @method StateProvider|NULL find($id, ?int $lockMode = NULL, ?int $lockVersion = NULL)
 * @method StateProvider|NULL findOneBy(array $criteria, array $orderBy = NULL)
 * @method StateProvider[] findAll()
 * @method StateProvider[] findBy(array $criteria, array $orderBy = NULL, ?int $limit = NULL, ?int $offset = NULL)
 * @extends AbstractRepository<StateProvider>
 */
class StateProviderRepository extends AbstractRepository
{

	public const STATES = [StateProviderRepository::STATE_FRESH, StateProviderRepository::STATE_BLOCKED, StateProviderRepository::STATE_ACTIVATED];
	public const STATE_ACTIVATED = 2;
	public const STATE_BLOCKED = 3;
	public const STATE_FRESH = 1;

	public function findById(int $id): ?StateProvider
	{
		return $this->find($id);
	}

}
