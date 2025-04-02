<?php

namespace App\UI\Modules\Front\Api;

use App\Domain\CategoryService\CategoryServiceFacade;
use App\Domain\City\CityFacade;
use App\Domain\Region\RegionFacade;
use App\UI\Modules\Front\BaseFrontPresenter;
use Doctrine\ORM\EntityManagerInterface;
use Nette\DI\Attributes\Inject;

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

}
