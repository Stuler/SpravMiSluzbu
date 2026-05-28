<?php declare(strict_types=1);

namespace App\Infrastructure\Stripe;

use Stripe\Stripe;
use Stripe\PaymentIntent;

final class StripeService
{
	private string $publicKey;
	private string $secretKey;

	/**
	 * @param array{publicKey: string, secretKey: string} $stripeConfig
	 */
	public function __construct(array $stripeConfig)
	{
		$this->publicKey = $stripeConfig['publicKey'];
		$this->secretKey = $stripeConfig['secretKey'];
	}

	private function init(): void
	{
		Stripe::setApiKey($this->secretKey);
	}

	public function getPublicKey(): string
	{
		return $this->publicKey;
	}

	public function getSecretKey(): string
	{
		return $this->secretKey;
	}

	/**
	 * @param array<string, string> $metadata
	 */
	public function createPaymentIntent(int $amount, string $currency = 'eur', array $metadata = []): PaymentIntent
	{
		$this->init();

		$payload = [
			'amount' => $amount,
			'currency' => $currency,
			'automatic_payment_methods' => ['enabled' => true],
		];

		if ($metadata !== []) {
			$payload['metadata'] = $metadata;
		}

		// @phpstan-ignore-next-line Stripe accepts metadata arrays in create params.
		return PaymentIntent::create($payload);
	}
}
