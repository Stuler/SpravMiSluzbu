<?php declare(strict_types=1);

namespace App\Domain\Matching;

use App\Domain\Demand\Demand;
use App\Domain\Provider\Provider;
use App\Model\Database\Entity\TId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'provider_invitation', indexes: [new ORM\Index(name: 'IDX_INVITATION_DELIVERY', columns: ['delivery'])], uniqueConstraints: [new ORM\UniqueConstraint(name: 'UNIQ_INVITATION_DEMAND_PROVIDER', columns: ['demand_id', 'provider_id'])])]
class ProviderInvitation
{
	use TId;
	#[ORM\Column(type: 'string', length: 16)]
	private string $response = 'pending';
	#[ORM\Column(type: 'string', length: 20)]
	private string $delivery = 'pending';
	#[ORM\Column(type: 'datetime_immutable')]
	private \DateTimeImmutable $createdAt;
	#[ORM\Column(type: 'datetime_immutable', nullable: true)]
	private ?\DateTimeImmutable $respondedAt = null;
	#[ORM\Column(type: 'datetime_immutable', nullable: true)]
	private ?\DateTimeImmutable $deliveryAttemptedAt = null;
	public function __construct(
		#[ORM\ManyToOne(targetEntity: Demand::class)] #[ORM\JoinColumn(nullable: false)] private Demand $demand,
		#[ORM\ManyToOne(targetEntity: Provider::class)] #[ORM\JoinColumn(nullable: false)] private Provider $provider,
		#[ORM\Column(type: 'string', length: 500)] private string $reason,
	) { $this->createdAt = new \DateTimeImmutable(); }
	public function getDemand(): Demand { return $this->demand; }
	public function getProvider(): Provider { return $this->provider; }
	public function getReason(): string { return $this->reason; }
	public function getResponse(): string { return $this->response; }
	public function getDelivery(): string { return $this->delivery; }
	public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
	public function getResponseLabel(): string
	{
		return match ($this->response) { 'accepted' => 'Má záujem', 'declined' => 'Nemá záujem', default => 'Čaká na reakciu' };
	}
	public function getDeliveryLabel(): string
	{
		return match ($this->delivery) { 'sent' => 'E-mail odoslaný', 'sending' => 'Odosielanie vyžaduje overenie', 'unknown' => 'Doručenie nepotvrdené', 'cancelled' => 'Odoslanie zrušené', default => 'Čaká na odoslanie' };
	}
	public function answer(bool $accept): void
	{
		if ($this->response !== 'pending') { return; }
		$this->response = $accept ? 'accepted' : 'declined';
		$this->respondedAt = new \DateTimeImmutable();
	}
	public function startDelivery(): void { $this->delivery = 'sending'; $this->deliveryAttemptedAt = new \DateTimeImmutable(); }
	public function finishDelivery(string $delivery): void { $this->delivery = $delivery; }
	public function retryDelivery(): void
	{
		if (!$this->canRetryDelivery()) {
			throw new \InvalidArgumentException('Delivery cannot be retried yet.');
		}
		$this->delivery = 'pending';
	}
	public function canRetryDelivery(): bool
	{
		return in_array($this->delivery, ['unknown', 'sending'], true) && $this->deliveryAttemptedAt !== null
			&& $this->deliveryAttemptedAt <= new \DateTimeImmutable('-5 minutes');
	}
}
