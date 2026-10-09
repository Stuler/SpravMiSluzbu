<?php declare(strict_types=1);

namespace App\Domain\SubscriptionPlan;

use App\Model\Database\Entity\TCreatedBy;
use App\Model\Database\Entity\TDateCreated;
use App\Model\Database\Entity\TDateDeleted;
use App\Model\Database\Entity\TDateModified;
use App\Model\Database\Entity\TDeletedBy;
use App\Model\Database\Entity\TId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'subscription_plan')]
class SubscriptionPlan
{
	use TId, TDateCreated, TDateModified, TDateDeleted, TCreatedBy, TDeletedBy;

	#[ORM\Column(type: 'string', length: 100, unique: true)]
	private string $name;

	#[ORM\Column(name: '`interval`', type: 'string', length: 50)]
	private string $interval; // e.g., monthly, yearly

	#[ORM\Column(type: 'integer')]
	private int $intervalCount; // e.g., 1 for monthly, 12 for yearly

	#[ORM\Column(type: 'integer')]
	private int $price; // price in cents

	#[ORM\Column(type: 'string', length: 100)]
	private string $stripePriceId;

	public function __construct(string $name, string $interval, int $intervalCount, int $price, string $stripePriceId)
	{
		if (trim($name) === '' || mb_strlen($name) > 100 || !in_array($interval, ['day', 'week', 'month', 'year'], true)
			|| $intervalCount < 1 || $price < 0 || trim($stripePriceId) === '' || strlen($stripePriceId) > 100) {
			throw new \InvalidArgumentException('Invalid subscription plan.');
		}
		$this->name = $name;
		$this->interval = $interval;
		$this->intervalCount = $intervalCount;
		$this->price = $price;
		$this->stripePriceId = $stripePriceId;
		$this->dateCreated = new \DateTime();
	}

	public function getName(): string { return $this->name; }
	public function getInterval(): string { return $this->interval; }
	public function getIntervalCount(): int { return $this->intervalCount; }
	public function getPrice(): int { return $this->price; }
	public function getStripePriceId(): string { return $this->stripePriceId; }
}
