<?php declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Demand\Demand;
use App\Domain\Matching\InvitationRepository;
use App\Domain\Matching\ProviderInvitation;
use App\Domain\Provider\Provider;
use App\Model\Database\EntityManagerDecorator;
use Doctrine\DBAL\LockMode;

final class DoctrineInvitationRepository implements InvitationRepository
{
    public function __construct(private EntityManagerDecorator $em) {}
    public function find(int $id): ?ProviderInvitation { return $this->em->find(ProviderInvitation::class, $id); }
    public function forDemand(Demand $demand): array
    { return $this->em->getRepository(ProviderInvitation::class)->findBy(['demand' => $demand], ['id' => 'ASC']); }
    public function forProvider(Provider $provider): array
    { return $this->em->getRepository(ProviderInvitation::class)->findBy(['provider' => $provider], ['createdAt' => 'DESC'], 100); }
    public function nextPendingForUpdate(): ?ProviderInvitation
    {
        $invitation = $this->em->createQueryBuilder()->select('i')->from(ProviderInvitation::class, 'i')
            ->where('i.delivery = :pending')->setParameter('pending', 'pending')->orderBy('i.id', 'ASC')->setMaxResults(1)
            ->getQuery()->setLockMode(LockMode::PESSIMISTIC_WRITE)->getOneOrNullResult();
        if ($invitation !== null) { $this->refreshForUpdate($invitation); }
        return $invitation;
    }
    public function refreshForUpdate(ProviderInvitation $invitation): void
    { $this->em->refresh($invitation, LockMode::PESSIMISTIC_WRITE); }
}
