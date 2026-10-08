<?php declare(strict_types=1);

namespace App\Domain\Provider\Exception;

use App\Model\Exception\LogicException;

/**
 * Thrown when a provider with the same normalized email already exists.
 */
final class DuplicateEmailException extends LogicException
{
}
