<?php declare(strict_types=1);

namespace App\Model\Database\Entity;

use Doctrine\ORM\Mapping as ORM;

trait TCreatedBy
{
	#[ORM\Column(type: 'string', length: 255, nullable: true)]
	private ?string $createdBy = null;

	public function getCreatedBy(): ?string
	{
		return $this->createdBy;
	}

	public function setCreatedBy(?string $createdBy): void
	{
		$this->createdBy = $createdBy;
	}
}
