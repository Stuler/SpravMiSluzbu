<?php declare(strict_types=1);

namespace App\Domain\Matching;

enum InvitationDeliveryStatus: string
{
    case Pending = 'pending';
    case Sending = 'sending';
    case Sent = 'sent';
    case Unknown = 'unknown';
    case Cancelled = 'cancelled';
}
