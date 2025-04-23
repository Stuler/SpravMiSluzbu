<?php

namespace App\Model\Provider\DTO;

class RegionDTO
{
    public function __construct(
        public int    $value,
        public string $label,
    )
    {
    }
}
