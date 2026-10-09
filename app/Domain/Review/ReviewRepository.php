<?php declare(strict_types=1);
namespace App\Domain\Review;
use App\Domain\Job\Job;
use App\Domain\Provider\Provider;
interface ReviewRepository
{
    public function find(int $id): ?Review;
    public function forJob(Job $job): ?Review;
    /** @return Review[] */ public function published(Provider $provider, int $page): array;
    /** @return array{count: int, average: ?float} */ public function summary(Provider $provider): array;
    /** @return Review[] */ public function pending(int $page): array;
    public function refreshForUpdate(Review $review): void;
}
