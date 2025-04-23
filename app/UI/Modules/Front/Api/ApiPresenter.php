<?php

namespace App\UI\Modules\Front\Api;

use App\Domain\CategoryService\CategoryServiceFacade;
use App\Domain\City\CityFacade;
use App\Domain\Region\RegionFacade;
use App\Infrastructure\Stripe\StripeService;
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
	public function createProvider()
	{
		$data = $this->getHttpRequest()->getPost();
		$category = $this->categoryServiceFacade->getCategoryById($data['category']);
		$region = $this->regionFacade->getRegionById($data['region']);
		$city = $this->cityFacade->getCityById($data['city']);
	}
}
