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
	#[ORM\Column(type: 'string', length: 16, enumType: InvitationResponse::class)]
	private InvitationResponse $response = InvitationResponse::Pending;
	#[ORM\Column(type: 'string', length: 20, enumType: InvitationDeliveryStatus::class)]
	private InvitationDeliveryStatus $delivery = InvitationDeliveryStatus::Pending;
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
	public function getResponse(): string { return $this->response->value; }
	public function getDelivery(): string { return $this->delivery->value; }
	public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
	public function getResponseLabel(): string
	{
		return match ($this->response) { InvitationResponse::Accepted => 'Má záujem', InvitationResponse::Declined => 'Nemá záujem', default => 'Čaká na reakciu' };
	}
	public function getDeliveryLabel(): string
	{
		return match ($this->delivery) { InvitationDeliveryStatus::Sent => 'E-mail odoslaný', InvitationDeliveryStatus::Sending => 'Odosielanie vyžaduje overenie', InvitationDeliveryStatus::Unknown => 'Doručenie nepotvrdené', InvitationDeliveryStatus::Cancelled => 'Odoslanie zrušené', default => 'Čaká na odoslanie' };
	}
	public function answer(bool $accept, ?\DateTimeImmutable $now = null): void
	{
		if ($this->response !== InvitationResponse::Pending) { return; }
		$this->response = $accept ? InvitationResponse::Accepted : InvitationResponse::Declined;
		$this->respondedAt = $now ?? new \DateTimeImmutable();
	}
	public function startDelivery(?\DateTimeImmutable $now = null): void
	{
		if ($this->delivery !== InvitationDeliveryStatus::Pending) { throw new \InvalidArgumentException('Delivery has already started.'); }
		$this->delivery = InvitationDeliveryStatus::Sending;
		$this->deliveryAttemptedAt = $now ?? new \DateTimeImmutable();
	}
	public function markDelivered(): void { $this->completeDelivery(InvitationDeliveryStatus::Sent); }
	public function markDeliveryUnknown(): void { $this->completeDelivery(InvitationDeliveryStatus::Unknown); }
	public function cancelDelivery(): void { $this->completeDelivery(InvitationDeliveryStatus::Cancelled); }
	private function completeDelivery(InvitationDeliveryStatus $status): void
	{
		if ($this->delivery === $status) { return; }
		if ($this->delivery !== InvitationDeliveryStatus::Sending) { throw new \InvalidArgumentException('Delivery is not in progress.'); }
		$this->delivery = $status;
	}
	public function retryDelivery(?\DateTimeImmutable $now = null): void
	{
		if (!$this->canRetryDelivery($now)) {
			throw new \InvalidArgumentException('Delivery cannot be retried yet.');
		}
		$this->delivery = InvitationDeliveryStatus::Pending;
	}
	public function canRetryDelivery(?\DateTimeImmutable $now = null): bool
	{
		return in_array($this->delivery, [InvitationDeliveryStatus::Unknown, InvitationDeliveryStatus::Sending], true) && $this->deliveryAttemptedAt !== null
			&& $this->deliveryAttemptedAt <= ($now ?? new \DateTimeImmutable())->modify('-5 minutes');
	}
}
