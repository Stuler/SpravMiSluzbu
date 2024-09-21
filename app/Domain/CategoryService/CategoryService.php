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

/**
 * @ORM\Entity
 * @ORM\Table(name="category_service")
 */
class CategoryService
{
	use TId, TDateCreated, TDateModified, TDateDeleted, TCreatedBy, TDeletedBy;

	/** @ORM\Column(type="string", length=255, nullable=FALSE, unique=false) */
	private string $name;

	/**
	 * @ORM\Column(type="text", nullable=true)
	 */
	private ?string $description = null;

	/**
	 * Relation to parent category
	 * @ORM\ManyToOne(targetEntity="CategoryService")
	 */
	private ?CategoryService $parent = null;

	public function __construct(
		string           $name,
		?string          $description = null,
		?CategoryService $parent = null,
		?User            $createdBy = null
	)
	{
		$this->name = $name;
		$this->description = $description;
		$this->parent = $parent;
		$this->dateCreated = new \DateTime();

		if ($createdBy !== null) {
			$this->setCreatedBy($createdBy);
		}
	}

	// Getters and setters...

	/**
	 * @throws InvalidArgumentException
	 */
	public function getParent(): ?CategoryService
	{
		return $this->parent;
	}

	/**
	 * @throws InvalidArgumentException
	 */
	public function setParent(?CategoryService $parent): void
	{
		if ($parent === $this) {
			throw new InvalidArgumentException('Category cannot be parent of itself');
		}
		$this->parent = $parent;
	}

	/**
	 * @throws InvalidArgumentException
	 */
	public function getDescription(): ?string
	{
		return $this->description;
	}

	/**
	 * @throws InvalidArgumentException
	 */
	public function setDescription(?string $description): void
	{
		$this->description = $description;
	}

	/**
	 * @throws InvalidArgumentException
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * @throws InvalidArgumentException
	 */
	public function setName(string $name): void
	{
		$this->name = $name;
	}
}
