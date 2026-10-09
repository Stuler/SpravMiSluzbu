<?php declare(strict_types=1);
namespace App\Domain\Job;
use App\Domain\Demand\{Demand, DemandFacade, DemandRepository};
use App\Domain\Matching\{InvitationRepository, InvitationFacade};
use App\Domain\Provider\{Provider, ProviderRepository};
use App\Domain\User\User;
use App\Domain\Review\ReviewRepository;
use App\Model\Database\EntityManagerDecorator;

final class JobFacade
{
    public function __construct(private EntityManagerDecorator $em, private DemandFacade $access, private DemandRepository $demands,
        private JobRepository $jobs, private InvitationRepository $invitations, private InvitationFacade $providerAccess,
        private ProviderRepository $providers, private ReviewRepository $reviews, private JobNotifications $notifications) {}
    public function current(Demand $demand): ?Job { return $this->jobs->current($demand); }
    /** @return Job[] */ public function forDemand(Demand $demand): array { return $this->jobs->forDemand($demand); }
    /** @return Job[] */ public function forProvider(Provider $provider): array { return $this->jobs->forProvider($provider); }
    public function select(Demand $demand, int $invitationId, ?User $customer, ?string $hash): Job
    {
        return $this->em->wrapInTransaction(function () use ($demand, $invitationId, $customer, $hash): Job {
            $this->demands->refreshForUpdate($demand);
            $this->requireOwner($demand, $customer, $hash);
            $invitation = $this->invitations->find($invitationId);
            if ($invitation === null || $invitation->getDemand()->getId() !== $demand->getId()) { throw new \InvalidArgumentException('Invalid invitation.'); }
            $this->invitations->refreshForUpdate($invitation);
            $current = $this->jobs->current($demand);
            if ($current !== null) {
                if ($current->getProvider()->getId() === $invitation->getProvider()->getId()) { return $current; }
                throw new \InvalidArgumentException('Another current job exists.');
            }
            $provider = $invitation->getProvider(); $this->providers->refreshForUpdate($provider);
            if ($demand->getStatus() === Demand::CLOSED || $invitation->getResponse() !== 'accepted'
                || $this->providerAccess->activeProvider($provider->getId()) === null || !$provider->acceptsDemands()) { throw new \InvalidArgumentException('Provider cannot be selected.'); }
            // A declined/cancelled agreement cannot be resurrected by a replayed selection POST.
            foreach ($this->jobs->forDemand($demand) as $previous) {
                if ($previous->getProvider()->getId() === $provider->getId()) { throw new \InvalidArgumentException('Select a replacement provider.'); }
            }
            $job = new Job($demand, $provider); $this->em->persist($job); $demand->stopMatching();
            $this->em->afterCommit(fn() => $this->notifications->agreement($job));
            return $job;
        });
    }
    /** All customer writes share the same authorization and lock order. */
    public function withCustomer(int $id, ?User $customer, ?string $hash, callable $operation): mixed
    {
        return $this->em->wrapInTransaction(function () use ($id, $customer, $hash, $operation): mixed {
            $job = $this->jobs->find($id);
            if ($job === null) { throw new \InvalidArgumentException('Job not found.'); }
            $this->demands->refreshForUpdate($job->getDemand()); $this->jobs->refreshForUpdate($job);
            $this->requireOwner($job->getDemand(), $customer, $hash);
            return $operation($job);
        });
    }
    private function requireOwner(Demand $demand, ?User $customer, ?string $hash): void
    {
        if ($demand->getVerifiedAt() === null || !$this->access->canAccess($demand, $customer, $hash)) { throw new \InvalidArgumentException('Verified demand owner required.'); }
    }
    public function cancel(int $id, ?User $customer, ?string $hash): void
    { $this->withCustomer($id, $customer, $hash, fn(Job $job) => $job->cancel()); }
    public function report(int $id, ?User $customer, ?string $hash, string $outcome, string $date, string $description): void
    {
        $parsed = $date === '' ? null : \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($parsed === false || ($parsed !== null && $parsed->format('Y-m-d') !== $date)) { throw new \InvalidArgumentException('Invalid work date.'); }
        $this->withCustomer($id, $customer, $hash, function (Job $job) use ($outcome, $parsed, $description): void {
            $job->report($outcome, $parsed, $description);
            if ($job->isReviewable()) { $this->em->afterCommit(fn() => $this->notifications->reviewInvitation($job)); }
        });
    }
    public function providerAction(int $id, Provider $provider, string $action, string $reason = ''): void
    {
        $this->em->wrapInTransaction(function () use ($id, $provider, $action, $reason): void {
            $job = $this->jobs->find($id);
            if ($job === null || $job->getProvider()->getId() !== $provider->getId()) { throw new \InvalidArgumentException('Job access denied.'); }
            $this->demands->refreshForUpdate($job->getDemand()); $this->jobs->refreshForUpdate($job); $this->providers->refreshForUpdate($provider);
            if ($this->providerAccess->activeProvider($provider->getId()) === null) { throw new \InvalidArgumentException('Provider unavailable.'); }
            if ($action === 'confirm') { $job->confirm(); }
            elseif ($action === 'decline') { $job->cancel(); }
            elseif ($action === 'dispute') {
                $review = $this->reviews->forJob($job);
                if ($review !== null && $review->getStatus() === \App\Domain\Review\Review::REJECTED) { throw new \InvalidArgumentException('Rejected review has already been decided.'); }
                $job->dispute($reason);
                if ($review !== null) { $this->reviews->refreshForUpdate($review); $review->awaitVerification(); }
            } else { throw new \InvalidArgumentException('Unknown job action.'); }
        });
    }
}
