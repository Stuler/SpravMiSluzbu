<?php declare(strict_types=1);

namespace App\Domain\Provider\DTO;

final class ProviderRegistrationData
{
	public function __construct(
		/** @var ServiceCategoryDTO[] */
		public array $serviceCategories = [],

		/** @var RegionDTO[] */
		public array $coveredRegions = [],

		public string $firstName = '',
		public string $lastName = '',
		public string $email = '',
		public string $password = '',
		public string $confirmPassword = '',
		public string $phone = '',
		public string $companyName = '',
		public string $ico = '',
		public string $street = '',
		public string $streetNumber = '',
		public string $cityId = '',
		public string $city = '',
		public string $zip = '',
		public bool $usePersonalAsContact = true,
		public string $contactFirstName = '',
		public string $contactLastName = '',
	)
	{
	}

	public function isValid(): bool
	{
		return $this->email !== ''
			&& filter_var($this->email, FILTER_VALIDATE_EMAIL) !== false
			&& $this->password !== ''
			&& $this->password === $this->confirmPassword
			&& $this->firstName !== ''
			&& $this->lastName !== ''
			&& $this->phone !== ''
			&& $this->companyName !== ''
			&& (int) $this->cityId > 0
			&& $this->zip !== ''
			&& $this->getServiceCategoryIds() !== []
			&& $this->getRegionIds() !== [];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toProviderData(): array
	{
		return [
			'serviceCategory' => $this->getServiceCategoryIds(),
			'region' => $this->getRegionIds(),
			'contactName' => $this->getContactFirstName(),
			'contactSurname' => $this->getContactLastName(),
			'contactTitle' => '',
			'email' => $this->email,
			'phoneNumber' => $this->phone,
			'companyName' => $this->companyName,
			'ico' => $this->ico,
			'dic' => '',
			'streetNo' => trim($this->street . ' ' . $this->streetNumber),
			'city' => (int) $this->cityId,
			'zipCode' => $this->zip,
			'password' => $this->password,
			'password2' => $this->confirmPassword,
		];
	}

	/**
	 * @return int[]
	 */
	private function getServiceCategoryIds(): array
	{
		return array_values(array_filter(
			array_map(
				static fn(ServiceCategoryDTO $category): int => $category->value,
				$this->serviceCategories
			),
			static fn(int $id): bool => $id > 0
		));
	}

	/**
	 * @return int[]
	 */
	private function getRegionIds(): array
	{
		return array_values(array_filter(
			array_map(
				static fn(RegionDTO $region): int => $region->value,
				$this->coveredRegions
			),
			static fn(int $id): bool => $id > 0
		));
	}

	private function getContactFirstName(): string
	{
		if ($this->usePersonalAsContact || $this->contactFirstName === '') {
			return $this->firstName;
		}

		return $this->contactFirstName;
	}

	private function getContactLastName(): string
	{
		if ($this->usePersonalAsContact || $this->contactLastName === '') {
			return $this->lastName;
		}

		return $this->contactLastName;
	}
}
