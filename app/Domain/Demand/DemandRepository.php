<?php declare(strict_types=1);

namespace App\Domain\Demand;

use App\Domain\User\User;

interface DemandRepository
{
    public function findBySubmissionKey(string $key): ?Demand;
    public function findByReference(string $reference): ?Demand;
    public function findByAccessHash(string $hash): ?Demand;
    /** @return Demand[] */
    public function forCustomer(User $customer, int $page): array;
    /** The caller owns a transaction; the selected demand is locked and refreshed. */
    public function nextReadyForUpdate(\DateTimeImmutable $now): ?Demand;
    public function refreshForUpdate(Demand $demand): void;
}
