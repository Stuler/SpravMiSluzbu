<?php declare(strict_types=1);

namespace App\Domain\Provider\Exception;

use App\Model\Exception\LogicException;

/**
 * Thrown when an activation token is missing, malformed, expired, or already consumed.
 */
final class InvalidActivationTokenException extends LogicException
{
}
