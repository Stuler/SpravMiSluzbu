<?php

namespace App\Model\Provider\DTO;

class ServiceCategoryDTO
{
	public function __construct(
		public int    $value,
		public string $label,
	)
	{
	}
}
