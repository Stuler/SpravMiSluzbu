<?php

namespace App\Model\Provider\DTO;

class ProviderRegistrationDataFactory
{
	/**
	 * Create DTO from raw request array (typically json-decoded)
	 */
	public static function fromArray(array $data): ProviderRegistrationData
	{
		$serviceCategories = array_map(
			fn(array $item) => new ServiceCategoryDTO(
				(int)($item['value'] ?? 0),
				(string)($item['label'] ?? '')
			),
			$data['serviceCategories'] ?? []
		);

		$coveredRegions = array_map(
			fn(array $item) => new RegionDTO(
				(int)($item['value'] ?? 0),
				(string)($item['label'] ?? '')
			),
			$data['coveredRegions'] ?? []
		);

		return new ProviderRegistrationData(
			$serviceCategories,
			$coveredRegions,
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
			(bool)($data['usePersonalAsContact'] ?? true),
			$data['contactFirstName'] ?? '',
			$data['contactLastName'] ?? '',
		);
	}
}
