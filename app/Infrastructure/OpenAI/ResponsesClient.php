<?php declare(strict_types=1);

namespace App\Infrastructure\OpenAI;

use App\Domain\Matching\AiUnavailableException;
use App\Domain\Matching\StructuredAiClient;

final class ResponsesClient implements StructuredAiClient
{
	public function complete(string $prompt, array $input, array $schema): array
	{
		$key = getenv('OPENAI_API_KEY');
		$model = getenv('OPENAI_MODEL');
		if (!$key || !$model) { throw new AiUnavailableException('OpenAI configuration is missing.'); }
		if (!function_exists('curl_init')) { throw new AiUnavailableException('The cURL extension is unavailable.'); }
		$started = microtime(true);
		$handle = curl_init('https://api.openai.com/v1/responses');
		curl_setopt_array($handle, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 60,
			CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key, 'Content-Type: application/json'],
			CURLOPT_POSTFIELDS => json_encode(['model' => $model, 'store' => false, 'instructions' => $prompt,
				'input' => json_encode($input, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'max_output_tokens' => 2000,
				'text' => ['format' => ['type' => 'json_schema', 'name' => 'matching_result', 'strict' => true, 'schema' => $schema]]], JSON_THROW_ON_ERROR),
		]);
		try {
			$body = curl_exec($handle);
			$status = curl_getinfo($handle, CURLINFO_HTTP_CODE);
			if (!is_string($body) || $status !== 200) { throw new \RuntimeException('OpenAI request failed.'); }
			$response = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
			if (($response['status'] ?? null) !== 'completed') { throw new \RuntimeException('OpenAI response was incomplete.'); }
			$text = '';
			foreach ($response['output'] ?? [] as $item) {
				foreach ($item['content'] ?? [] as $content) {
					if (($content['type'] ?? '') === 'refusal') { throw new \RuntimeException('OpenAI declined the request.'); }
					if (($content['type'] ?? '') === 'output_text') { $text .= $content['text']; }
				}
			}
			$data = json_decode($text, true, 64, JSON_THROW_ON_ERROR);
			if (!is_array($data)) { throw new \RuntimeException('Invalid AI result.'); }
			return ['data' => $data, 'model' => (string) ($response['model'] ?? $model),
				'inputTokens' => (int) ($response['usage']['input_tokens'] ?? 0), 'outputTokens' => (int) ($response['usage']['output_tokens'] ?? 0),
				'durationMs' => (int) ((microtime(true) - $started) * 1000)];
		} finally { curl_close($handle); }
	}
}
