<?php declare(strict_types=1);

namespace App\Domain\Provider\Exception;

use App\Model\Exception\RuntimeException;

/**
 * Thrown when credentials are correct but the provider account is still in the fresh
 * (not yet activated) state. Signals that the activation email flow must be completed.
 */
final class ProviderNotActivatedException extends RuntimeException
{
}
