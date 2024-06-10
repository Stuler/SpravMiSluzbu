<?php declare(strict_types=1);

namespace App\Model\Database\Entity;

use Doctrine\ORM\Mapping as ORM;

trait TCreatedBy
{
	/**
	 * @ORM\Column(type="integer", nullable=true)
	 */
	private int $createdBy;

	public function getCreatedBy(): int
	{
		return $this->createdBy;
	}

	public function setCreatedBy(int $createdBy): void
	{
		$this->createdBy = $createdBy;
	}
}
