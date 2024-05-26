<?php declare(strict_types=1);

namespace App\Model\Database\Entity;

use Doctrine\ORM\Mapping as ORM;

trait TDateCreated
{
	/**
	 * @ORM\Column(type="datetime", options={"default": "CURRENT_TIMESTAMP"})
	 */
	private \DateTime $dateCreated;

	public function getDateCreated(): \DateTime
	{
		return $this->dateCreated;
	}

	public function setDateCreated(\DateTime $dateCreated): void
	{
		$this->dateCreated = $dateCreated;
	}
}
