<?php

namespace App\Domain\Region;

use App\Model\Database\Entity\TId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'region')]
class Region
{
	use TId;

	#[ORM\Column(type: 'string', length: 255)]
	private string $name;

	#[ORM\Column(type: 'string', length: 2)]
	private string $shortcut;

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

	public function getShortcut(): string
	{
		return $this->shortcut;
	}

	public function setShortcut(string $shortcut): void
	{
		$this->shortcut = $shortcut;
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
