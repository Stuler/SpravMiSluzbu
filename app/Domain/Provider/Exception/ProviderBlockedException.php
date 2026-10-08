<?php declare(strict_types=1);

namespace App\Domain\Provider\Exception;

use App\Model\Exception\RuntimeException;

/**
 * Thrown when a blocked provider attempts to sign in.
 */
final class ProviderBlockedException extends RuntimeException
{
}
