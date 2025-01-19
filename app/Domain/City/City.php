<?php

namespace App\Domain\City;

use App\Domain\District\District;
use App\Domain\Region\Region;
use App\Model\Database\Entity\TId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'city')]
class City
{
	use TId;

	#[ORM\Column(type: 'string', length: 255)]
	private string $fullname;

	#[ORM\Column(type: 'string', length: 255)]
	private string $shortname;

	#[ORM\Column(type: 'string', length: 6)]
	private string $zip;

	#[ORM\ManyToOne(targetEntity: Region::class)]
	#[ORM\JoinColumn(name: 'region_id', referencedColumnName: 'id')]
	private Region $region;

	#[ORM\ManyToOne(targetEntity: District::class)]
	#[ORM\JoinColumn(name: 'district_id', referencedColumnName: 'id')]
	private District $district;

	#[ORM\Column(type: 'boolean', options: ['default' => 1])]
	private bool $use;

	// Getters and setters...
	public function getId(): int
	{
		return $this->id;
	}

	public function getFullname(): string
	{
		return $this->fullname;
	}

	public function setFullname(string $fullname): void
	{
		$this->fullname = $fullname;
	}

	public function getShortname(): string
	{
		return $this->shortname;
	}

	public function setShortname(string $shortname): void
	{
		$this->shortname = $shortname;
	}

	public function getZip(): string
	{
		return $this->zip;
	}

	public function setZip(string $zip): void
	{
		$this->zip = $zip;
	}

	public function getDistrict(): District
	{
		return $this->district;
	}

	public function setDistrict(District $district): void
	{
		$this->district = $district;
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
