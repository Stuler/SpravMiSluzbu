<?php declare(strict_types=1);

namespace App\Domain\Matching;

enum InvitationResponse: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
}
