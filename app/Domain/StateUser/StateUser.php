<?php declare(strict_types=1);

namespace App\Domain\StateUser;

use App\Model\Database\Entity\TCreatedBy;
use App\Model\Database\Entity\TDateCreated;
use App\Model\Database\Entity\TDateDeleted;
use App\Model\Database\Entity\TDateModified;
use App\Model\Database\Entity\TDeletedBy;
use App\Model\Database\Entity\TId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;

#[ORM\Entity]
#[ORM\Table(name: 'state_user')]
class StateUser
{
	use TId, TDateCreated, TDateModified, TDateDeleted, TCreatedBy, TDeletedBy;

	#[ORM\Column(type: 'string', length: 32, nullable: false)]
	private string $name;

	private EntityManagerInterface $entityManager;

	public function __construct(string $name, EntityManagerInterface $entityManager)
	{
		$this->name = $name;
		$this->entityManager = $entityManager;
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function setName(string $name): void
	{
		$this->name = $name;
	}

	/**
	 * Return state entity by id
	 * @param int $id
	 * @return StateUser
	 */
	public function getState(int $id): StateUser
	{
		if (!in_array($id, StateUserRepository::STATES)) {
			throw new InvalidArgumentException('Invalid state id');
		}

		return $this->entityManager->getRepository(StateUser::class)->find($id);
	}

	public function getById(int $id): StateUser
	{
		return $this->entityManager->getRepository(StateUser::class)->find($id);
	}

	public function setId(int $stateId): void
	{
		$this->id = $stateId;
	}
}
