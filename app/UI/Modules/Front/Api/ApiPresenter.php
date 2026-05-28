<?php

namespace App\UI\Modules\Front\Api;

use App\Domain\CategoryService\CategoryServiceFacade;
use App\Domain\City\CityFacade;
use App\Domain\Provider\DTO\ProviderRegistrationDataFactory;
use App\Domain\Provider\ProviderFacade;
use App\Domain\Region\RegionFacade;
use App\Infrastructure\Stripe\StripeService;
use App\UI\Modules\Front\BaseFrontPresenter;
use Nette\Application\AbortException;
use Nette\DI\Attributes\Inject;

class ApiPresenter extends BaseFrontPresenter
{
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
		$data = json_decode($this->getHttpRequest()->getRawBody() ?? '', true);
		$metadata = [];
		if (is_array($data) && isset($data['userId'])) {
			$metadata['provider_id'] = (string) $data['userId'];
		}

		$paymentIntent = $this->stripeService->createPaymentIntent(1000, 'eur', $metadata);

		$this->sendJson([
			'clientSecret' => $paymentIntent->client_secret,
		]);
	}

	/**
	 * Returns the Stripe public key for the client-side integration.
	 */
	public function actionGetStripePublicKey(): void
	{
		$this->sendJson([
			'publicKey' => $this->stripeService->getPublicKey(),
		]);
	}

	/**
	 * Creates a new provider. Returns Stripe PaymentIntent.
	 * @return void
	 */
	public function actionCreateProvider(): void
	{
		$data = json_decode($this->getHttpRequest()->getRawBody() ?? '', true);

		if (!is_array($data)) {
			$this->getHttpResponse()->setCode(400);
			$this->sendJson([
				'code' => 400,
				'message' => 'Invalid JSON structure.',
				'result' => [],
			]);
		}

		$dto = ProviderRegistrationDataFactory::fromArray($data);

		if (!$dto->isValid()) {
			$this->getHttpResponse()->setCode(400);
			$this->sendJson([
				'code' => 400,
				'message' => 'Invalid input data.',
				'result' => [],
			]);
		}

		try {
			$result = $this->providerFacade->registerWithSubscription($dto);
			$this->sendJson([
				'code' => 200,
				'message' => 'Provider created successfully.',
				'userId' => $result['userId'],
				'providerId' => $result['providerId'],
				'result' => $result,
			]);
		} catch (AbortException $e) {
			throw $e;
		} catch (\Throwable $e) {
			$this->getHttpResponse()->setCode(500);
			$this->sendJson([
				'code' => 500,
				'message' => $e->getMessage(),
				'result' => [],
			]);
		}

	}
}
