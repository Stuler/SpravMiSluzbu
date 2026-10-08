<?php declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Demand\Demand;
use App\Domain\Demand\DemandRepository;
use App\Domain\User\User;
use App\Model\Database\EntityManagerDecorator;
use Doctrine\DBAL\LockMode;

final class DoctrineDemandRepository implements DemandRepository
{
    public function __construct(private EntityManagerDecorator $em) {}
    public function findBySubmissionKey(string $key): ?Demand
    { return $this->em->getRepository(Demand::class)->findOneBy(['submissionKey' => $key]); }
    public function findByReference(string $reference): ?Demand
    { return $this->em->getRepository(Demand::class)->findOneBy(['reference' => $reference]); }
    public function findByAccessHash(string $hash): ?Demand
    { return $this->em->getRepository(Demand::class)->findOneBy(['accessTokenHash' => $hash]); }
    public function forCustomer(User $customer, int $page): array
    { return $this->em->getRepository(Demand::class)->findBy(['customer' => $customer], ['createdAt' => 'DESC'], 21, (max(1, $page) - 1) * 20); }

    public function nextReadyForUpdate(\DateTimeImmutable $now): ?Demand
    {
        $demand = $this->em->createQueryBuilder()->select('d')->from(Demand::class, 'd')
            ->where('d.status = :status AND d.verifiedAt IS NOT NULL')
            ->andWhere('(d.processAfter IS NULL OR d.processAfter <= :now) AND (d.leaseUntil IS NULL OR d.leaseUntil <= :now)')
            ->setParameter('status', Demand::READY)->setParameter('now', $now)->orderBy('d.id', 'ASC')->setMaxResults(1)
            ->getQuery()->setLockMode(LockMode::PESSIMISTIC_WRITE)->getOneOrNullResult();
        if ($demand !== null) { $this->refreshForUpdate($demand); }
        return $demand;
    }

    public function refreshForUpdate(Demand $demand): void
    { $this->em->refresh($demand, LockMode::PESSIMISTIC_WRITE); }
}
