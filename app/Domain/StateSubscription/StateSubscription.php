<?php

namespace App\Domain\StateSubscription;

use App\Domain\Subscription\Subscription;
use App\Model\Database\Entity\TCreatedBy;
use App\Model\Database\Entity\TDateCreated;
use App\Model\Database\Entity\TDateDeleted;
use App\Model\Database\Entity\TDateModified;
use App\Model\Database\Entity\TDeletedBy;
use App\Model\Database\Entity\TId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'state_subscription')]
class StateSubscription
{

	use TId, TDateCreated, TDateModified, TDateDeleted, TCreatedBy, TDeletedBy;

	#[ORM\Column(type: 'string', length: 50, unique: true)]
	private string $name;

	#[ORM\OneToMany(mappedBy: 'status', targetEntity: Subscription::class)]
	private $subscriptions;

	public function __construct(string $name)
	{
		$this->name = $name;
	}

	public function getId(): int
	{
		return $this->id;
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
