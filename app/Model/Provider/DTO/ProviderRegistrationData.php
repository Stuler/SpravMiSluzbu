<?php

namespace App\Model\Provider\DTO;

class ProviderRegistrationData
{
	public function __construct(
		/** @var ServiceCategoryDTO[] */
		public array  $serviceCategories = [],

		/** @var RegionDTO[] */
		public array  $coveredRegions = [],

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
		public bool   $usePersonalAsContact = true,
		public string $contactFirstName = '',
		public string $contactLastName = '',
	)
	{
	}

	public function isValid(): bool
	{
		return
			$this->email !== '' &&
			filter_var($this->email, FILTER_VALIDATE_EMAIL) &&
			$this->password !== '' &&
			$this->password === $this->confirmPassword;
	}
}
