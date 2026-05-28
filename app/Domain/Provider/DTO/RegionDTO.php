<?php declare(strict_types=1);

namespace App\Domain\Provider\DTO;

final class RegionDTO
{
	public function __construct(
		public int $value,
		public string $label,
	)
	{
	}
}
