<?php declare(strict_types=1);
namespace App\Domain\Admin;

use App\Domain\Demand\Demand;
use App\Domain\Matching\MatchingFacade;
use App\Domain\Matching\InvitationFacade;
use App\Model\Database\EntityManagerDecorator;

final class AdminDemandFacade
{
    public function __construct(private EntityManagerDecorator $em, private AdminAuditFacade $audit,
        private MatchingFacade $matching, private InvitationFacade $invitations) {}
    public function requeue(Demand $demand, ?int $category): void
    {
        $this->audit->requireAdmin();
        $this->change(function () use ($demand, $category): void {
            $this->matching->requeue($demand, $category);
            $this->audit->record('demand', $demand->getId(), 'demand_requeued', ['category_id' => $category]);
        });
    }
    public function approve(Demand $demand, array $providers): void
    {
        $this->audit->requireAdmin();
        $this->change(function () use ($demand, $providers): void {
            $this->matching->approveManually($demand, $providers);
            $this->audit->record('demand', $demand->getId(), 'providers_approved', ['provider_ids' => array_map('intval', $providers)]);
        });
    }
    public function retry(Demand $demand, int $invitation): void
    {
        $this->audit->requireAdmin();
        $this->change(function () use ($demand, $invitation): void {
            $this->invitations->retryDelivery($invitation, $demand);
            $this->audit->record('demand', $demand->getId(), 'delivery_retried', ['invitation_id' => $invitation]);
        });
    }
    private function change(callable $operation): void
    {
        $this->em->wrapInTransaction($operation);
    }
}
