<?php

namespace App\UI\Modules\Front\Api;

use App\Domain\CategoryService\CategoryService;
use App\Domain\City\City;
use App\Domain\Region\Region;
use App\UI\Modules\Front\BaseFrontPresenter;
use Doctrine\ORM\EntityManagerInterface;
use Nette\DI\Attributes\Inject;

class ApiPresenter extends BaseFrontPresenter
{

	#[Inject]
	public EntityManagerInterface $entityManager;

	public function actionDefault(): void
	{
		$this->sendJson([
			'status' => 'ok',
			'message' => 'API is working',
		]);
	}

	/**
	 * Returns a list of categories as JSON for the provider sign-up form.
	 * @return void
	 */
	public function actionCategories(): void
	{
		$categories = $this->entityManager->getRepository(CategoryService::class)->findAll();
		$data = array_map(fn($category) => [
			'id' => $category->getId(),
			'name' => $category->getName(),
		], $categories);

		$this->sendJson($data);
	}

	/**
	 * Returns a list of regions as JSON for the provider sign-up form.
	 * @return void
	 */
	public function actionRegions(): void
	{
		$regions = $this->entityManager->getRepository(Region::class)->findAll();
		$data = array_map(fn($region) => [
			'id' => $region->getId(),
			'name' => $region->getName(),
		], $regions);
		$this->sendJson($data);
	}

	/**
	 * Returns a list of cities as JSON for the provider sign-up form.
	 * @return void
	 */
	public function actionCities(): void
	{
		$cities = $this->entityManager->getRepository(City::class)->findAll();
		$data = array_map(fn($city) => [
			'id' => $city->getId(),
			'name' => $city->getFullName(),
			'region_id' => $city->getRegion()->getId(),
			'zip' => $city->getZip(),
		], $cities);
		$this->sendJson($data);
	}

}
