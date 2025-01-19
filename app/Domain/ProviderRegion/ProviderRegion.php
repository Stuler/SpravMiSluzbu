<?php

namespace App\Domain\ProviderRegion;

use App\Domain\CategoryService\CategoryService;
use App\Domain\Provider\Provider;
use App\Domain\Region\Region;
use App\Model\Database\Entity\TCreatedBy;
use App\Model\Database\Entity\TDateCreated;
use App\Model\Database\Entity\TDateDeleted;
use App\Model\Database\Entity\TDateModified;
use App\Model\Database\Entity\TDeletedBy;
use App\Model\Database\Entity\TId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "provider_region")]
class ProviderRegion
{

	use TId, TDateCreated, TDateModified, TDateDeleted, TCreatedBy, TDeletedBy;

	public function __construct(
		#[ORM\ManyToOne(targetEntity: Provider::class, inversedBy: "regions")]
		#[ORM\JoinColumn(nullable: false)]
		private Provider $provider,

		#[ORM\ManyToOne(targetEntity: Region::class, inversedBy: "providers")]
		#[ORM\JoinColumn(nullable: false)]
		private Region   $region
	)
	{
		$this->dateCreated = new \DateTime();
	}

	public function getId(): ?int
	{
		return $this->id;
	}

	public function getProvider(): ?Provider
	{
		return $this->provider;
	}

	public function setProvider(?Provider $provider): self
	{
		$this->provider = $provider;

		return $this;
	}

	public function getCategoryService(): ?CategoryService
	{
		return $this->serviceCategory;
	}

	public function setCategoryService(?CategoryService $serviceCategory): self
	{
		$this->serviceCategory = $serviceCategory;

		return $this;
	}
}
