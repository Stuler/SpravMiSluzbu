<?php

namespace App\Domain\District;

use App\Domain\Region\Region;
use App\Model\Database\Entity\TId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'district')]
class District
{
	use TId;

	#[ORM\Column(type: 'string', length: 255)]
	private string $name;

	#[ORM\Column(type: 'string', length: 255)]
	private string $vehRegNum;

	#[ORM\Column(type: 'smallint')]
	private int $code;

	#[ORM\ManyToOne(targetEntity: Region::class)]
	#[ORM\JoinColumn(name: 'region_id', referencedColumnName: 'id')]
	private Region $region;

	#[ORM\Column(type: 'boolean', options: ['default' => 1])]
	private bool $use;

	// Getters and setters...
	public function getId(): int
	{
		return $this->id;
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function setName(string $name): void
	{
		$this->name = $name;
	}

	public function getVehRegNum(): string
	{
		return $this->vehRegNum;
	}

	public function setVehRegNum(string $vehRegNum): void
	{
		$this->vehRegNum = $vehRegNum;
	}

	public function getCode(): int
	{
		return $this->code;
	}

	public function setCode(int $code): void
	{
		$this->code = $code;
	}

	public function getRegion(): Region
	{
		return $this->region;
	}

	public function setRegion(Region $region): void
	{
		$this->region = $region;
	}

	public function getUse(): bool
	{
		return $this->use;
	}

	public function setUse(bool $use): void
	{
		$this->use = $use;
	}
}
