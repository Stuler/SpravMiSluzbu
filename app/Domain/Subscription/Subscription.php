<?php declare(strict_types=1);

namespace App\Domain\Subscription;

use App\Domain\Provider\Provider;
use App\Domain\StateSubscription\StateSubscription;
use App\Domain\SubscriptionPlan\SubscriptionPlan;
use App\Model\Database\Entity\TCreatedBy;
use App\Model\Database\Entity\TDateCreated;
use App\Model\Database\Entity\TDateDeleted;
use App\Model\Database\Entity\TDateModified;
use App\Model\Database\Entity\TDeletedBy;
use App\Model\Database\Entity\TId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'subscription')]
class Subscription
{

	use TId, TDateCreated, TDateModified, TDateDeleted, TCreatedBy, TDeletedBy;

	#[ORM\ManyToOne(targetEntity: Provider::class)]
	#[ORM\JoinColumn(name: 'provider_id', referencedColumnName: 'id', nullable: false)]
	private Provider $provider;

	#[ORM\Column(type: 'string', length: 100)]
	private string $stripeSubscriptionId;

	#[ORM\ManyToOne(targetEntity: SubscriptionPlan::class)]
	#[ORM\JoinColumn(name: 'subscription_plan_id', referencedColumnName: 'id', nullable: false)]
	private SubscriptionPlan $plan;

	#[ORM\Column(type: 'string', enumType: StateSubscription::class)]
	private StateSubscription $status;

	#[ORM\Column(type: 'datetime')]
	private \DateTime $startedAt;

	#[ORM\Column(type: 'datetime', nullable: true)]
	private ?\DateTime $endsAt = null;

	#[ORM\Column(type: 'datetime', nullable: true)]
	private ?\DateTime $canceledAt = null;

	#[ORM\Column(type: 'datetime')]
	private \DateTime $createdAt;

	public function __construct(Provider $provider, string $stripeSubscriptionId, SubscriptionPlan $plan)
	{
		$this->provider = $provider;
		$this->stripeSubscriptionId = $stripeSubscriptionId;
		$this->plan = $plan;
		$this->status = SubscriptionStatus::Pending;
		$this->startedAt = new \DateTime();
		$this->createdAt = new \DateTime();
	}

	// Getters and setters...
}
