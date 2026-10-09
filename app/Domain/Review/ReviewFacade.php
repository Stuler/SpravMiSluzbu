<?php declare(strict_types=1);
namespace App\Domain\Review;
use App\Domain\Job\{Job, JobFacade, JobRepository, JobNotifications};
use App\Domain\User\User;
use App\Domain\Provider\{Provider, ProviderRepository};
use App\Domain\Matching\InvitationFacade;
use App\Domain\Admin\AdminAuditFacade;
use App\Domain\Demand\DemandRepository;
use App\Model\Database\EntityManagerDecorator;
final class ReviewFacade
{
    public function __construct(private EntityManagerDecorator $em, private JobFacade $jobs, private JobRepository $jobRepository,
        private ReviewRepository $reviews, private InvitationFacade $providerAccess, private ProviderRepository $providers,
        private DemandRepository $demands, private \App\Domain\Demand\DemandFacade $access, private AdminAuditFacade $admin, private JobNotifications $notifications) {}
    public function forJob(Job $job): ?Review { return $this->reviews->forJob($job); }
    public function canReview(Job $job, ?User $customer, ?string $hash): bool
    {
        return $job->isReviewable() && $this->reviews->forJob($job) === null
            && $job->getDemand()->getVerifiedAt() !== null
            && $this->canAccess($job, $customer, $hash);
    }
    private function canAccess(Job $job, ?User $customer, ?string $hash): bool
    {
        // Use the same domain access policy as every demand operation.
        return $this->access->canAccess($job->getDemand(), $customer, $hash);
    }
    public function submit(int $id, ?User $customer, ?string $hash, int $rating, string $text): Review
    {
        return $this->jobs->withCustomer($id, $customer, $hash, function (Job $job) use ($rating, $text): Review {
            $existing = $this->reviews->forJob($job);
            if ($existing !== null) { return $existing; }
            $review = new Review($job, $rating, $text); $this->em->persist($review);
            $this->em->afterCommit(fn() => $this->notifications->newReview($review));
            return $review;
        });
    }
    public function reply(int $id, Provider $provider, string $text): void
    {
        $this->em->wrapInTransaction(function () use ($id, $provider, $text): void {
            $review = $this->reviews->find($id);
            if ($review === null || $review->getJob()->getProvider()->getId() !== $provider->getId()) { throw new \InvalidArgumentException('Review access denied.'); }
            $this->demands->refreshForUpdate($review->getJob()->getDemand()); $this->jobRepository->refreshForUpdate($review->getJob());
            $this->providers->refreshForUpdate($provider); $this->reviews->refreshForUpdate($review);
            if ($this->providerAccess->activeProvider($provider->getId()) === null) { throw new \InvalidArgumentException('Provider unavailable.'); }
            $review->reply($text);
        });
    }
    /** @return Review[] */ public function pending(int $page): array { $this->admin->requireAdmin(); return $this->reviews->pending($page); }
    /** @return Job[] */ public function disputed(): array { $this->admin->requireAdmin(); return $this->em->getRepository(Job::class)->findBy(['verification' => Job::DISPUTED], ['id' => 'ASC'], 100); }
    public function findForAdmin(int $id): ?Review { $this->admin->requireAdmin(); return $this->reviews->find($id); }
    public function decide(int $id, bool $publish, string $reason): void
    {
        $this->em->wrapInTransaction(function () use ($id, $publish, $reason): void {
            $actor = $this->admin->requireAdmin(); $review = $this->reviews->find($id);
            if ($review === null) { throw new \InvalidArgumentException('Review not found.'); }
            $this->demands->refreshForUpdate($review->getJob()->getDemand()); $this->jobRepository->refreshForUpdate($review->getJob()); $this->reviews->refreshForUpdate($review);
            $review->decide($publish, $reason, $actor);
            $this->admin->record('review', $id, $publish ? 'publish' : 'reject', ['reason' => trim($reason)]);
        });
    }
    public function resolveJob(int $id, bool $verified, string $reason): void
    {
        $this->em->wrapInTransaction(function () use ($id, $verified, $reason): void {
            $this->admin->requireAdmin(); $job = $this->jobRepository->find($id);
            if ($job === null || trim($reason) === '' || mb_strlen($reason) > 1000) { throw new \InvalidArgumentException('Job and decision reason required.'); }
            $this->demands->refreshForUpdate($job->getDemand()); $this->jobRepository->refreshForUpdate($job);
            if ($this->reviews->forJob($job) !== null) { throw new \InvalidArgumentException('Resolve the associated review instead.'); }
            if ($job->getVerification() !== Job::DISPUTED) { throw new \InvalidArgumentException('Job is not disputed.'); }
            if ($verified) { $job->verifyByAdmin(); } else { $job->rejectVerification(); if (!$job->isReviewable()) { $job->cancel(); } }
            $this->admin->record('job', $id, $verified ? 'verify' : 'reject_collaboration', ['reason' => trim($reason)]);
        });
    }
    /** @return Review[] */ public function published(Provider $provider, int $page = 1): array { return $this->reviews->published($provider, $page); }
    /** @return array{count: int, average: ?float} */ public function summary(Provider $provider): array { return $this->reviews->summary($provider); }
}
