<?php declare(strict_types=1);

namespace App\Model\Database\Entity;

use Doctrine\ORM\Mapping as ORM;

trait TDeletedBy
{
	#[ORM\Column(type: 'string', length: 255, nullable: true)]
	private ?string $deletedBy = null;

	public function getDeletedBy(): ?string
	{
		return $this->deletedBy;
	}

	public function setDeletedBy(?string $deletedBy): void
	{
		$this->deletedBy = $deletedBy;
	}
}
