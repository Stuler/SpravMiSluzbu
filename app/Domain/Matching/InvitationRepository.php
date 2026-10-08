<?php declare(strict_types=1);

namespace App\Domain\Matching;

use App\Domain\Demand\Demand;
use App\Domain\Provider\Provider;

interface InvitationRepository
{
    public function find(int $id): ?ProviderInvitation;
    /** @return ProviderInvitation[] */
    public function forDemand(Demand $demand): array;
    /** @return ProviderInvitation[] */
    public function forProvider(Provider $provider): array;
    public function nextPendingForUpdate(): ?ProviderInvitation;
    public function refreshForUpdate(ProviderInvitation $invitation): void;
}
