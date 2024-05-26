<?php declare(strict_types=1);

namespace App\Model\Database\Entity;

use Doctrine\ORM\Mapping as ORM;

trait TDeletedBy
{
	/**
	 * @ORM\Column(type="integer", nullable=true)
	 */
	private ?int $deletedBy = null;

	public function getDeletedBy(): ?int
	{
		return $this->deletedBy;
	}

	public function setDeletedBy(?int $deletedBy): void
	{
		$this->deletedBy = $deletedBy;
	}
}
