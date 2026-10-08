<?php declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Demand\Demand;
use App\Domain\Provider\Provider;
use App\Domain\Provider\ProviderRepository;
use App\Domain\ProviderRegion\ProviderRegion;
use App\Domain\ProviderServiceCategory\ProviderServiceCategory;
use App\Domain\StateProvider\StateProvider;
use App\Model\Database\EntityManagerDecorator;
use Doctrine\DBAL\LockMode;

final class DoctrineProviderRepository implements ProviderRepository
{
    public function __construct(private EntityManagerDecorator $em) {}
    public function find(int $id): ?Provider { return $this->em->find(Provider::class, $id); }
    public function findByEmail(string $email): ?Provider
    { return $this->em->getRepository(Provider::class)->findOneBy(['email' => $email]); }
    public function findByActivationHash(string $hash): ?Provider
    { return $this->em->getRepository(Provider::class)->findOneBy(['activationTokenHash' => $hash]); }
    public function findByActivationHashForUpdate(string $hash): ?Provider
    {
        $provider = $this->em->createQueryBuilder()->select('p')->from(Provider::class, 'p')
            ->where('p.activationTokenHash = :hash')->setParameter('hash', $hash)
            ->getQuery()->setLockMode(LockMode::PESSIMISTIC_WRITE)->getOneOrNullResult();
        if ($provider !== null) { $this->refreshForUpdate($provider); }
        return $provider;
    }
    public function refreshForUpdate(Provider $provider): void
    { $this->em->refresh($provider, LockMode::PESSIMISTIC_WRITE); }
    public function emailExists(string $email): bool
    {
        // Usable after an insert conflict has closed the ORM manager and rolled back.
        return $this->em->getConnection()->fetchOne('SELECT id FROM provider WHERE email = ?', [$email]) !== false;
    }
    /**
     * @param int[] $categoryIds
     * @return Provider[]
     */
    public function eligibleForDemand(Demand $demand, array $categoryIds): array
    {
        if ($categoryIds === []) { return []; }
        return $this->em->createQueryBuilder()->select('DISTINCT p')->from(Provider::class, 'p')
            ->innerJoin(ProviderRegion::class, 'pr', 'WITH', 'pr.provider = p')
            ->innerJoin(ProviderServiceCategory::class, 'pc', 'WITH', 'pc.provider = p')
            ->innerJoin('pc.serviceCategory', 'c')
            ->where('p.stateProvider = :active AND p.administrativelyBlocked = false AND p.dateDeleted IS NULL AND p.acceptsDemands = true')
            ->andWhere('pr.region = :region AND pr.dateDeleted IS NULL AND pc.dateDeleted IS NULL AND c.dateDeleted IS NULL')
            ->andWhere('c.id IN (:categories)')->setParameter('active', StateProvider::ACTIVATED)
            ->setParameter('region', $demand->getCity()->getRegion()->getId())->setParameter('categories', $categoryIds)
            ->orderBy('p.id', 'ASC')->setMaxResults(50)->getQuery()->getResult();
    }
}
