<?php declare(strict_types=1);

namespace App\Domain\Provider;

use App\Domain\Demand\Demand;

interface ProviderRepository
{
    public function find(int $id): ?Provider;
    public function findByEmail(string $email): ?Provider;
    public function findByActivationHash(string $hash): ?Provider;
    public function findByActivationHashForUpdate(string $hash): ?Provider;
    public function refreshForUpdate(Provider $provider): void;
    public function emailExists(string $email): bool;
    /**
     * @param int[] $categoryIds
     * @return Provider[]
     */
    public function eligibleForDemand(Demand $demand, array $categoryIds): array;
}
