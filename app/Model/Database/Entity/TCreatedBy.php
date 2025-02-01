<?php declare(strict_types=1);

namespace App\Model\Database\Entity;

use App\Domain\User\User;
use Doctrine\ORM\Mapping as ORM;

trait TCreatedBy
{
	#[ORM\ManyToOne(targetEntity: User::class)]
	#[ORM\JoinColumn(name: 'created_by', referencedColumnName: 'id')]
	private ?User $createdBy = null;

	public function getCreatedBy(): ?User
	{
		return $this->createdBy;
	}

	public function setCreatedBy(User $createdBy): void
	{
		$this->createdBy = $createdBy;
	}
}
