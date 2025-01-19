<?php declare(strict_types=1);

namespace App\Domain\CategoryService;

use App\Domain\User\User;
use App\Model\Database\Entity\TCreatedBy;
use App\Model\Database\Entity\TDateCreated;
use App\Model\Database\Entity\TDateDeleted;
use App\Model\Database\Entity\TDateModified;
use App\Model\Database\Entity\TDeletedBy;
use App\Model\Database\Entity\TId;
use App\Model\Exception\Logic\InvalidArgumentException;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'category_service')]
class CategoryService
{
	use TId, TDateCreated, TDateModified, TDateDeleted, TCreatedBy, TDeletedBy;

	public function __construct(
		#[ORM\Column(type: 'string', length: 255, nullable: false)]
		private string           $name,

		#[ORM\Column(type: 'text', nullable: true)]
		private ?string          $description = null,

		#[ORM\ManyToOne(targetEntity: CategoryService::class)]
		#[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id')]
		private ?CategoryService $parent = null,

		?User                    $createdBy = null
	)
	{
		$this->dateCreated = new \DateTime();

		if ($createdBy !== null) {
			$this->setCreatedBy($createdBy);
		}
	}

	// Getters and setters...

	public function getParent(): ?CategoryService
	{
		return $this->parent;
	}

	public function setParent(?CategoryService $parent): void
	{
		if ($parent === $this) {
			throw new InvalidArgumentException('Category cannot be parent of itself');
		}
		$this->parent = $parent;
	}

	public function getDescription(): ?string
	{
		return $this->description;
	}

	public function setDescription(?string $description): void
	{
		$this->description = $description;
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function setName(string $name): void
	{
		$this->name = $name;
	}
}
