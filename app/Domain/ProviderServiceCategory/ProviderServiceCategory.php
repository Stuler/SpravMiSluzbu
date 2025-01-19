<?php

namespace App\Domain\ProviderServiceCategory;

use App\Domain\CategoryService\CategoryService;
use App\Domain\Provider\Provider;
use App\Model\Database\Entity\TCreatedBy;
use App\Model\Database\Entity\TDateCreated;
use App\Model\Database\Entity\TDateDeleted;
use App\Model\Database\Entity\TDateModified;
use App\Model\Database\Entity\TDeletedBy;
use App\Model\Database\Entity\TId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "provider_service_category")]
class ProviderServiceCategory
{
	use TId, TDateCreated, TDateModified, TDateDeleted, TCreatedBy, TDeletedBy;

	public function __construct(
		#[ORM\ManyToOne(targetEntity: Provider::class, inversedBy: "serviceCategories")]
		#[ORM\JoinColumn(nullable: false)]
		private Provider        $provider,

		#[ORM\ManyToOne(targetEntity: CategoryService::class, inversedBy: "providers")]
		#[ORM\JoinColumn(nullable: false)]
		private CategoryService $serviceCategory
	)
	{
		$this->dateCreated = new \DateTime();
	}

	public function getId(): ?int
	{
		return $this->id;
	}

	public function getProvider(): Provider
	{
		return $this->provider;
	}

	public function getCategoryService(): CategoryService
	{
		return $this->serviceCategory;
	}
}
