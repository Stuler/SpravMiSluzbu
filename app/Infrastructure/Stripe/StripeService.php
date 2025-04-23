<?php declare(strict_types=1);

namespace App\Infrastructure\Stripe;

use Stripe\Stripe;
use Stripe\PaymentIntent;

final class StripeService
{
	private string $publicKey;
	private string $secretKey;

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

	public function createPaymentIntent(int $amount, string $currency = 'eur'): PaymentIntent
	{
		$this->init();

		return PaymentIntent::create([
			'amount' => $amount,
			'currency' => $currency,
			'automatic_payment_methods' => ['enabled' => true],
		]);
	}
}
