<?php declare(strict_types=1);

namespace App\Model\Database\Entity;

use Doctrine\ORM\Mapping as ORM;

trait TDateModified
{
	#[ORM\Column(type: 'datetime', nullable: true)]
	private ?\DateTime $dateModified = null;

	public function getDateModified(): ?\DateTime
	{
		return $this->dateModified;
	}

	public function setDateModified(\DateTime $dateModified): void
	{
		$this->dateModified = $dateModified;
	}
}
