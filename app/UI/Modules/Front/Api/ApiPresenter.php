<?php

namespace App\UI\Modules\Front\Api;

use App\Domain\CategoryService\CategoryServiceFacade;
use App\Domain\City\CityFacade;
use App\Domain\Provider\ProviderFacade;
use App\Domain\Region\RegionFacade;
use App\Infrastructure\Stripe\StripeService;
use App\Model\Provider\DTO\ProviderRegistrationData;
use App\UI\Modules\Front\BaseFrontPresenter;
use Doctrine\ORM\EntityManagerInterface;
use Nette\DI\Attributes\Inject;
use Stripe\Stripe;
use Stripe\PaymentIntent;

class ApiPresenter extends BaseFrontPresenter
{

	#[Inject]
	public EntityManagerInterface $entityManager;

	#[Inject]
	public CategoryServiceFacade $categoryServiceFacade;

	#[Inject]
	public RegionFacade $regionFacade;

	#[Inject]
	public CityFacade $cityFacade;

	#[Inject]
	public StripeService $stripeService;

	#[Inject]
	public ProviderFacade $providerFacade;

	public function actionDefault(): void
	{
		$this->sendJson([
			'status' => 'ok',
			'message' => 'API is working',
		]);
	}

	/**
	 * Returns a list of categories as JSON for the provider sign-up form.
	 */
	public function actionCategories(): void
	{
		$result = $this->categoryServiceFacade->getGroupedChildCategoriesForSelect();
		$this->sendJson($result);
	}

	/**
	 * Returns a list of regions as JSON for the provider sign-up form.
	 */
	public function actionRegions(): void
	{
		$data = $this->regionFacade->getRegionsForSelect();
		$this->sendJson($data);
	}

	/**
	 * Returns a list of cities as JSON for the provider sign-up form.
	 * @return void
	 */
	public function actionCities(): void
	{
		$data = $this->cityFacade->getCitiesForSelect();
		$this->sendJson($data);
	}

	/**
	 * Creates a Stripe payment intent and returns the client secret.
	 * @return void
	 */
	public function actionCreatePaymentIntent(): void
	{
		$paymentIntent = $this->stripeService->createPaymentIntent(1000);
		bdump($paymentIntent);

		$this->sendJson([
			'clientSecret' => $paymentIntent->client_secret,
		]);
	}

	/**
	 * Returns the Stripe public key for the client-side integration.
	 */
	public function actionGetStripePublicKey(): void
	{
		bdump($this->stripeService->getPublicKey());
		$this->sendJson([
			'publicKey' => $this->stripeService->getPublicKey(),
		]);
	}

	/**
	 * Creates a new provider. Returns Stripe PaymentIntent.
	 * @return void
	 */
	public function actionCreateProvider()
	{
		$request = $this->getHttpRequest();
		$data = json_decode($request->getRawBody(), true);

		if (!is_array($data)) {
			$this->sendJson([
				'code' => 500,
				'message' => 'Invalid JSON structure.',
				'result' => [],
			]);
		}

		$data = json_decode($this->getHttpRequest()->getRawBody(), true);

		$dto = new ProviderRegistrationData(
			$data['serviceCategories'] ?? [],
			$data['coveredRegions'] ?? [],
			$data['firstName'] ?? '',
			$data['lastName'] ?? '',
			$data['email'] ?? '',
			$data['password'] ?? '',
			$data['confirmPassword'] ?? '',
			$data['phone'] ?? '',
			$data['companyName'] ?? '',
			$data['ico'] ?? '',
			$data['street'] ?? '',
			$data['streetNumber'] ?? '',
			$data['cityId'] ?? '',
			$data['city'] ?? '',
			$data['zip'] ?? '',
			$data['usePersonalAsContact'] ?? true,
			$data['contactFirstName'] ?? '',
			$data['contactLastName'] ?? '',
			$data['subscriptionPlan'] ?? 'free',
			$data['cardNumber'] ?? '',
			$data['cardExpiry'] ?? '',
			$data['cardCvc'] ?? '',
		);

		if (!$dto->isValid()) {
			$this->sendJson([
				'code' => 500,
				'message' => 'Invalid input data.',
				'result' => [],
			]);
		}

		try {
			$result = $this->providerFacade->registerWithSubscription($dto);
			$this->sendJson([
				'code' => 200,
				'message' => 'Provider created successfully.',
				'result' => $result,
			]);
		} catch (\Throwable $e) {
			$this->sendJson([
				'code' => 500,
				'message' => $e->getMessage(),
				'result' => [],
			]);
		}

	}
}
