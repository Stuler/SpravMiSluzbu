<?php declare(strict_types=1);
namespace App\Infrastructure\Persistence\Doctrine;
use App\Domain\Review\{Review, ReviewRepository};
use App\Domain\Job\Job;
use App\Domain\Provider\Provider;
use App\Model\Database\EntityManagerDecorator;
use Doctrine\DBAL\LockMode;
final class DoctrineReviewRepository implements ReviewRepository
{
    public function __construct(private EntityManagerDecorator $em) {}
    public function find(int $id): ?Review { return $this->em->find(Review::class, $id); }
    public function forJob(Job $job): ?Review
    {
        $query = $this->em->createQueryBuilder()->select('r')->from(Review::class, 'r')->where('r.job = :job')->setParameter('job', $job)->getQuery();
        // A locking read observes a concurrent commit even under MySQL REPEATABLE READ.
        $locked = $this->em->getConnection()->isTransactionActive();
        if ($locked) { $query->setLockMode(LockMode::PESSIMISTIC_WRITE); }
        $review = $query->getOneOrNullResult();
        if ($locked && $review !== null) { $this->refreshForUpdate($review); }
        return $review;
    }
    private function publicQuery(Provider $provider): \Doctrine\ORM\QueryBuilder
    { return $this->em->createQueryBuilder()->from(Review::class, 'r')->join('r.job', 'j')->where('j.provider = :provider AND r.status = :status')->setParameter('provider', $provider)->setParameter('status', Review::PUBLISHED); }
    public function published(Provider $provider, int $page): array
    { return $this->publicQuery($provider)->select('r', 'j')->orderBy('r.publishedAt', 'DESC')->addOrderBy('r.id', 'DESC')->setMaxResults(20)->setFirstResult((max(1, $page) - 1) * 20)->getQuery()->getResult(); }
    public function summary(Provider $provider): array
    { $row = $this->publicQuery($provider)->select('COUNT(r.id) AS total, AVG(r.rating) AS average')->getQuery()->getSingleResult(); return ['count' => (int) $row['total'], 'average' => $row['average'] === null ? null : (float) $row['average']]; }
    public function pending(int $page): array
    { return $this->em->getRepository(Review::class)->findBy(['status' => Review::PENDING], ['id' => 'ASC'], 20, (max(1, $page) - 1) * 20); }
    public function refreshForUpdate(Review $review): void { $this->em->refresh($review, LockMode::PESSIMISTIC_WRITE); }
}
