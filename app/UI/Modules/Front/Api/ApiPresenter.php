<?php

namespace App\UI\Modules\Front\Api;

use App\UI\Modules\Front\BaseFrontPresenter;

class ApiPresenter extends BaseFrontPresenter
{

	public function actionDefault(): void
	{
		$this->sendJson([
			'status' => 'ok',
			'message' => 'API is working',
		]);
	}

	public function actionCategories(): void
	{
		// Simulate data retrieval
		$data = [

			['id' => 1, 'name' => 'Category 1'],
			['id' => 2, 'name' => 'Category 2'],
			['id' => 3, 'name' => 'Category 3'],

		];
		$this->sendJson($data);
	}

	public function actionRegions(): void
	{
		// Simulate data retrieval
		$data = [
			['id' => 1, 'name' => 'Region 1'],
			['id' => 2, 'name' => 'Region 2'],
			['id' => 3, 'name' => 'Region 3'],
		];
		$this->sendJson($data);
	}

	public function actionCities(): void
	{
		// Simulate data retrieval
		$data = [
			'cities' => [
				['id' => 1, 'name' => 'City 1'],
				['id' => 2, 'name' => 'City 2'],
				['id' => 3, 'name' => 'City 3'],
			],
		];
		$this->sendJson($data);
	}

}
