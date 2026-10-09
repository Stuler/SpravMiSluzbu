<?php declare(strict_types=1);
namespace App\Infrastructure\Persistence\Doctrine;
use App\Domain\Job\{Job, JobRepository};
use App\Domain\Demand\Demand;
use App\Domain\Provider\Provider;
use App\Model\Database\EntityManagerDecorator;
use Doctrine\DBAL\LockMode;
final class DoctrineJobRepository implements JobRepository
{
    public function __construct(private EntityManagerDecorator $em) {}
    public function find(int $id): ?Job { return $this->em->find(Job::class, $id); }
    public function current(Demand $demand): ?Job { return $this->em->getRepository(Job::class)->findOneBy(['activeDemand' => $demand]); }
    public function forDemand(Demand $demand): array { return $this->em->getRepository(Job::class)->findBy(['demand' => $demand], ['id' => 'DESC']); }
    public function forProvider(Provider $provider): array { return $this->em->getRepository(Job::class)->findBy(['provider' => $provider], ['id' => 'DESC'], 100); }
    public function refreshForUpdate(Job $job): void { $this->em->refresh($job, LockMode::PESSIMISTIC_WRITE); }
}
