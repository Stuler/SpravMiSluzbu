<?php declare(strict_types=1);

namespace App\Domain\Provider\Exception;

use App\Model\Exception\LogicException;

/**
 * Thrown when authoritative server-side validation of provider registration fails.
 */
final class RegistrationValidationException extends LogicException
{
}
