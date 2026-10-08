<?php declare(strict_types=1);

namespace App\Domain\StateSubscription;

use App\Domain\Subscription\Subscription;
use App\Model\Database\Entity\TCreatedBy;
use App\Model\Database\Entity\TDateCreated;
use App\Model\Database\Entity\TDateDeleted;
use App\Model\Database\Entity\TDateModified;
use App\Model\Database\Entity\TDeletedBy;
use App\Model\Database\Entity\TId;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
#[ORM\Table(name: 'state_subscription')]
class StateSubscription
{

	use TId, TDateCreated, TDateModified, TDateDeleted, TCreatedBy, TDeletedBy;

	#[ORM\Column(type: 'string', length: 50, unique: true)]
	private string $name;

	/** @var Collection<int, Subscription> */
	#[ORM\OneToMany(mappedBy: 'status', targetEntity: Subscription::class)]
	private Collection $subscriptions;

	public function __construct(string $name)
	{
		$this->setName($name);
		$this->subscriptions = new ArrayCollection();
		$this->dateCreated = new \DateTime();
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
		$name = trim($name);
		if ($name === '' || mb_strlen($name) > 50) {
			throw new \InvalidArgumentException('Invalid subscription state name.');
		}
		$this->name = $name;
	}

	public function addSubscription(Subscription $subscription): void
	{
		if ($subscription->getStatus() !== $this) {
			throw new \InvalidArgumentException('Subscription belongs to a different state.');
		}
		if (!$this->subscriptions->contains($subscription)) { $this->subscriptions->add($subscription); }
	}

	/** @return Subscription[] */
	public function getSubscriptions(): array { return $this->subscriptions->toArray(); }
}
