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

	#[ORM\ManyToOne(targetEntity: StateSubscription::class, inversedBy: 'subscriptions')]
	#[ORM\JoinColumn(name: 'state_subscription_id', referencedColumnName: 'id', nullable: false)]
	private StateSubscription $status;

	#[ORM\Column(type: 'datetime_immutable')]
	private \DateTimeImmutable $startedAt;

	#[ORM\Column(type: 'datetime_immutable', nullable: true)]
	private ?\DateTimeImmutable $endsAt = null;

	#[ORM\Column(type: 'datetime_immutable', nullable: true)]
	private ?\DateTimeImmutable $canceledAt = null;

	public function __construct(Provider $provider, string $stripeSubscriptionId, SubscriptionPlan $plan,
		StateSubscription $initialState, ?\DateTimeImmutable $now = null)
	{
		if (trim($stripeSubscriptionId) === '' || strlen($stripeSubscriptionId) > 100) {
			throw new \InvalidArgumentException('Invalid subscription identifier.');
		}
		$now ??= new \DateTimeImmutable();
		$this->provider = $provider;
		$this->stripeSubscriptionId = $stripeSubscriptionId;
		$this->plan = $plan;
		$this->status = $initialState;
		$this->startedAt = $now;
		$this->dateCreated = \DateTime::createFromImmutable($now);
		$initialState->addSubscription($this);
	}

	public function getProvider(): Provider { return $this->provider; }
	public function getStripeSubscriptionId(): string { return $this->stripeSubscriptionId; }
	public function getPlan(): SubscriptionPlan { return $this->plan; }
	public function getStatus(): StateSubscription { return $this->status; }
	public function getStartedAt(): \DateTimeImmutable { return $this->startedAt; }
	public function getEndsAt(): ?\DateTimeImmutable { return $this->endsAt; }
	public function getCanceledAt(): ?\DateTimeImmutable { return $this->canceledAt; }
}
