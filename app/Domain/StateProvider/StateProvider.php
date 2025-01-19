<?php declare(strict_types=1);

namespace App\Domain\StateProvider;

use App\Model\Database\Entity\TCreatedBy;
use App\Model\Database\Entity\TDateCreated;
use App\Model\Database\Entity\TDateDeleted;
use App\Model\Database\Entity\TDateModified;
use App\Model\Database\Entity\TDeletedBy;
use App\Model\Database\Entity\TId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'state_provider')]
class StateProvider
{
	use TId, TDateCreated, TDateModified, TDateDeleted, TCreatedBy, TDeletedBy;

	#[ORM\Column(type: 'string', length: 32, unique: false, nullable: false)]
	private string $name;

	public function setId(int $id): void
	{
		$this->id = $id;
	}
}
