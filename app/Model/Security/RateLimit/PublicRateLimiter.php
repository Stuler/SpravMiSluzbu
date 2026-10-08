<?php declare(strict_types=1);

namespace App\Model\Security\RateLimit;

/** Shared across PHP workers on a single host; stores hashed keys only. */
final class PublicRateLimiter
{
	public function __construct(private string $directory) {}

	public function consume(string $key, int $limit, int $windowSeconds): bool
	{
		if (!is_dir($this->directory) && !@mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
			throw new \RuntimeException('Cannot create rate limiter directory.');
		}
		$file = fopen($this->directory . '/' . hash('sha256', $key) . '.json', 'c+');
		if ($file === false) {
			throw new \RuntimeException('Cannot open rate limiter storage.');
		}
		try {
			if (!flock($file, LOCK_EX)) {
				throw new \RuntimeException('Cannot lock rate limiter storage.');
			}
			$state = json_decode(stream_get_contents($file) ?: '{}', true) ?: [];
			$now = time();
			if (($state['until'] ?? 0) <= $now) {
				$state = ['until' => $now + $windowSeconds, 'count' => 0];
			}
			if ($state['count'] >= $limit) {
				return false;
			}
			$state['count']++;
			ftruncate($file, 0);
			rewind($file);
			fwrite($file, json_encode($state, JSON_THROW_ON_ERROR));
			fflush($file);
			return true;
		} finally {
			flock($file, LOCK_UN);
			fclose($file);
		}
	}
}
