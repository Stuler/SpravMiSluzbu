<?php declare(strict_types = 1);

use App\Domain\Provider\ActivationToken;
use Tester\Assert;

require_once __DIR__ . '/../../../../bootstrap.php';

// Raw token is base64url, hash is its SHA-256, expiry is ~24h away.
test(function (): void {
	$token = ActivationToken::generate();

	// base64url: no +, /, or = padding.
	Assert::match('#^[A-Za-z0-9_-]+$#', $token->raw);

	// Stored hash is the SHA-256 of the raw token (64 hex chars).
	Assert::same(64, strlen($token->hash));
	Assert::same(hash('sha256', $token->raw), $token->hash);
	Assert::same(ActivationToken::hashRaw($token->raw), $token->hash);

	// Raw token must not equal its stored hash (raw is never the stored value).
	Assert::notSame($token->raw, $token->hash);
});

// Expiry is 24 hours in the future.
test(function (): void {
	$token = ActivationToken::generate();
	$now = new \DateTime();

	Assert::true($token->expiresAt > $now);
	$diffHours = ($token->expiresAt->getTimestamp() - $now->getTimestamp()) / 3600;
	Assert::true($diffHours > 23.9 && $diffHours <= 24.1);
});

// Each generated token is unique.
test(function (): void {
	$a = ActivationToken::generate();
	$b = ActivationToken::generate();

	Assert::notSame($a->raw, $b->raw);
	Assert::notSame($a->hash, $b->hash);
});

// hashRaw is deterministic.
test(function (): void {
	Assert::same(ActivationToken::hashRaw('abc'), ActivationToken::hashRaw('abc'));
	Assert::notSame(ActivationToken::hashRaw('abc'), ActivationToken::hashRaw('abd'));
});
