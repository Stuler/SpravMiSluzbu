<?php declare(strict_types=1);

namespace App\Domain\Demand;

enum DemandStatus: string
{
    case PendingVerification = 'pending_verification';
    case Ready = 'ready';
    case Closed = 'closed';
    case NeedsReview = 'needs_review';
    case NoProviders = 'no_providers';
    case ProvidersInvited = 'providers_invited';
}
