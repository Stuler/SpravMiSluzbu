<?php declare(strict_types=1);

namespace App\Model\Database\Entity;

use Doctrine\ORM\Mapping as ORM;

trait TDateDeleted
{
	/**
	 * @ORM\Column(type="datetime", nullable=true)
	 */
	private ?\DateTime $dateDeleted = null;

	public function getDateDeleted(): ?\DateTime
	{
		return $this->dateDeleted;
	}

	public function setDateDeleted(?\DateTime $dateDeleted): void
	{
		$this->dateDeleted = $dateDeleted;
	}
}
