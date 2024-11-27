<?php declare(strict_types=1);

// src/Model/Database/Entity/TCreatedBy.php

namespace App\Model\Database\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Domain\User\User;

trait TCreatedBy
{
	/**
	 * @ORM\ManyToOne(targetEntity="App\Domain\User\User")
	 * @ORM\JoinColumn(name="created_by", referencedColumnName="id", nullable=true)
	 */
	private ?User $createdBy = null;

	public function getCreatedBy(): ?User
	{
		return $this->createdBy;
	}

	public function setCreatedBy(?User $createdBy): void
	{
		$this->createdBy = $createdBy;
	}
}
