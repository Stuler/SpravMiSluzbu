<?php declare(strict_types=1);
namespace App\Domain\Job;
use App\Domain\Demand\Demand;
use App\Domain\Provider\Provider;
interface JobRepository
{
    public function find(int $id): ?Job;
    public function current(Demand $demand): ?Job;
    /** @return Job[] */ public function forDemand(Demand $demand): array;
    /** @return Job[] */ public function forProvider(Provider $provider): array;
    public function refreshForUpdate(Job $job): void;
}
