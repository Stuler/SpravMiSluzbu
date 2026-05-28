<?php declare(strict_types=1);

namespace App\Domain\Provider\DTO;

final class ProviderRegistrationDataFactory
{
	/**
	 * Create DTO from raw request array.
	 *
	 * @param array<string, mixed> $data
	 */
	public static function fromArray(array $data): ProviderRegistrationData
	{
		return new ProviderRegistrationData(
			self::mapServiceCategories($data['serviceCategories'] ?? []),
			self::mapRegions($data['coveredRegions'] ?? []),
			self::stringValue($data['firstName'] ?? ''),
			self::stringValue($data['lastName'] ?? ''),
			self::stringValue($data['email'] ?? ''),
			self::stringValue($data['password'] ?? ''),
			self::stringValue($data['confirmPassword'] ?? ''),
			self::stringValue($data['phone'] ?? ''),
			self::stringValue($data['companyName'] ?? ''),
			self::stringValue($data['ico'] ?? ''),
			self::stringValue($data['street'] ?? ''),
			self::stringValue($data['streetNumber'] ?? ''),
			self::stringValue($data['cityId'] ?? ''),
			self::stringValue($data['city'] ?? ''),
			self::stringValue($data['zip'] ?? ''),
			(bool) ($data['usePersonalAsContact'] ?? true),
			self::stringValue($data['contactFirstName'] ?? ''),
			self::stringValue($data['contactLastName'] ?? ''),
		);
	}

	/**
	 * @param mixed $items
	 * @return ServiceCategoryDTO[]
	 */
	private static function mapServiceCategories(mixed $items): array
	{
		if (!is_array($items)) {
			return [];
		}

		$result = [];
		foreach ($items as $item) {
			if (!is_array($item)) {
				continue;
			}

			$result[] = new ServiceCategoryDTO(
				(int) ($item['value'] ?? 0),
				(string) ($item['label'] ?? '')
			);
		}

		return $result;
	}

	/**
	 * @param mixed $items
	 * @return RegionDTO[]
	 */
	private static function mapRegions(mixed $items): array
	{
		if (!is_array($items)) {
			return [];
		}

		$result = [];
		foreach ($items as $item) {
			if (!is_array($item)) {
				continue;
			}

			$result[] = new RegionDTO(
				(int) ($item['value'] ?? 0),
				(string) ($item['label'] ?? '')
			);
		}

		return $result;
	}

	private static function stringValue(mixed $value): string
	{
		if (!is_scalar($value)) {
			return '';
		}

		return (string) $value;
	}
}
