<?php declare(strict_types=1);

namespace App\Model\Database\Entity;

use App\Domain\User\User;
use Doctrine\ORM\Mapping as ORM;

trait TDeletedBy
{
	#[ORM\ManyToOne(targetEntity: User::class)]
	#[ORM\JoinColumn(name: 'deleted_by', referencedColumnName: 'id')]
	private ?User $deletedBy = null;

	public function getDeletedBy(): ?User
	{
		return $this->deletedBy;
	}

	public function setDeletedBy(User $deletedBy): void
	{
		$this->deletedBy = $deletedBy;
	}
}
