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
		$seen = [];
		for ($ancestor = $parent; $ancestor !== null; $ancestor = $ancestor->getParent()) {
			$key = spl_object_id($ancestor);
			if ($ancestor === $this || (isset($this->id) && isset($ancestor->id) && $ancestor->getId() === $this->id) || isset($seen[$key])) {
				throw new \InvalidArgumentException('Category hierarchy cannot contain a cycle.');
			}
			$seen[$key] = true;
		}
		$this->parent = $parent;
	}

	public function isSelectable(): bool
	{
		$seen = [];
		for ($category = $this; $category !== null; $category = $category->getParent()) {
			$key = spl_object_id($category);
			if ($category->getDateDeleted() !== null || isset($seen[$key])) { return false; }
			$seen[$key] = true;
		}
		return true;
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
