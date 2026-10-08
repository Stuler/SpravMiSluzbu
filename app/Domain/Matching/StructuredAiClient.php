<?php declare(strict_types=1);

namespace App\Domain\Matching;

interface StructuredAiClient
{
	/** @return array{data: array, model: string, inputTokens: int, outputTokens: int, durationMs: int} */
	public function complete(string $prompt, array $input, array $schema): array;
}
