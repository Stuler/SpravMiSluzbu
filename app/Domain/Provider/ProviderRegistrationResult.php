<?php declare(strict_types=1);

namespace App\Domain\Provider;

/**
 * Outcome of a provider registration.
 *
 * The account is always created transactionally; {@see $activationMailSent} reports
 * whether the activation email could be delivered so the UI can offer a resend.
 */
final class ProviderRegistrationResult
{
	public function __construct(
		public readonly int $providerId,
		public readonly string $email,
		public readonly bool $activationMailSent,
	)
	{
	}
}
