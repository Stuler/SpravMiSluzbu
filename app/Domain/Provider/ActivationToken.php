<?php declare(strict_types=1);

namespace App\Domain\Provider;

/**
 * Secure single-use provider activation token.
 *
 * The raw token is delivered to the user (in the activation email) and must
 * never be persisted or logged. Only its SHA-256 hash is stored in the database.
 */
final class ActivationToken
{
	public const TTL_HOURS = 24;
	private const RANDOM_BYTES = 32;

	private function __construct(
		public readonly string $raw,
		public readonly string $hash,
		public readonly \DateTimeImmutable $expiresAt,
	)
	{
	}

	/**
	 * Generate a fresh token with a 24 hour expiration.
	 */
	public static function generate(?\DateTimeImmutable $now = null): self
	{
		$raw = self::base64UrlEncode(random_bytes(self::RANDOM_BYTES));

		return new self(
			$raw,
			self::hashRaw($raw),
			($now ?? new \DateTimeImmutable())->modify('+' . self::TTL_HOURS . ' hours'),
		);
	}

	/**
	 * Hash a raw token for storage/lookup. Never store the raw token.
	 */
	public static function hashRaw(string $raw): string
	{
		return hash('sha256', $raw);
	}

	private static function base64UrlEncode(string $data): string
	{
		return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
	}
}
