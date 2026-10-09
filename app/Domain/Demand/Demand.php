<?php declare(strict_types=1);

namespace App\Domain\Demand;

use App\Domain\CategoryService\CategoryService;
use App\Domain\City\City;
use App\Domain\User\User;
use App\Model\Database\Entity\TId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'demand', indexes: [new ORM\Index(name: 'IDX_DEMAND_QUEUE', columns: ['status', 'process_after', 'lease_until'])])]
class Demand
{
	use TId;
	#[ORM\Column(type: 'boolean', options: ['default' => false])]
	private bool $matchingStopped = false;
	public function isMatchingStopped(): bool { return $this->matchingStopped; }
	public function stopMatching(): void { $this->matchingStopped = true; $this->leaseToken = null; $this->leaseUntil = null; $this->processAfter = null; }
	public const PENDING = 'pending_verification';
	public const READY = 'ready';
	public const CLOSED = 'closed';
	public const REVIEW = 'needs_review';
	public const NO_PROVIDERS = 'no_providers';
	public const INVITED = 'providers_invited';

	#[ORM\Column(type: 'integer', options: ['default' => 0])]
	private int $processingAttempts = 0;
	#[ORM\Column(type: 'datetime_immutable', nullable: true)]
	private ?\DateTimeImmutable $processAfter = null;
	#[ORM\Column(type: 'datetime_immutable', nullable: true)]
	private ?\DateTimeImmutable $leaseUntil = null;
	#[ORM\Column(type: 'string', length: 32, nullable: true)]
	private ?string $leaseToken = null;
	#[ORM\Column(type: 'string', length: 255, nullable: true)]
	private ?string $processingNote = null;
	#[ORM\Column(type: 'text', nullable: true)]
	private ?string $serviceSummary = null;

	#[ORM\Column(type: 'string', length: 32, enumType: DemandStatus::class)]
	private DemandStatus $status = DemandStatus::PendingVerification;
	#[ORM\Column(type: 'datetime_immutable')]
	private \DateTimeImmutable $createdAt;
	#[ORM\Column(type: 'datetime_immutable', nullable: true)]
	private ?\DateTimeImmutable $verifiedAt = null;
	#[ORM\Column(type: 'datetime_immutable', nullable: true)]
	private ?\DateTimeImmutable $closedAt = null;
	#[ORM\Column(type: 'string', length: 64, nullable: true, unique: true)]
	private ?string $accessTokenHash = null;
	#[ORM\Column(type: 'datetime_immutable', nullable: true)]
	private ?\DateTimeImmutable $accessExpiresAt = null;

	public function __construct(
		#[ORM\Column(type: 'string', length: 32, unique: true)]
		private string $reference,
		#[ORM\Column(type: 'string', length: 64, unique: true)]
		private string $submissionKey,
		#[ORM\Column(type: 'text')]
		private string $description,
		#[ORM\ManyToOne(targetEntity: City::class)]
		#[ORM\JoinColumn(nullable: false)]
		private City $city,
		#[ORM\ManyToOne(targetEntity: CategoryService::class)]
		#[ORM\JoinColumn(nullable: true)]
		private ?CategoryService $category,
		#[ORM\Column(type: 'string', length: 255)]
		private string $requestedTerm,
		#[ORM\Column(type: 'string', length: 255)]
		private string $contactName,
		#[ORM\Column(type: 'string', length: 100)]
		private string $contactEmail,
		#[ORM\Column(type: 'string', length: 32, nullable: true)]
		private ?string $contactPhone,
		#[ORM\ManyToOne(targetEntity: User::class)]
		#[ORM\JoinColumn(nullable: true)]
		private ?User $customer,
	) {
		$this->createdAt = new \DateTimeImmutable();
		if ($customer !== null) {
			$this->verify();
		}
	}

	public function getReference(): string { return $this->reference; }
	public function getDescription(): string { return $this->description; }
	public function getCity(): City { return $this->city; }
	public function getCategory(): ?CategoryService { return $this->category; }
	public function getRequestedTerm(): string { return $this->requestedTerm; }
	public function getContactName(): string { return $this->contactName; }
	public function getContactEmail(): string { return $this->contactEmail; }
	public function getContactPhone(): ?string { return $this->contactPhone; }
	public function getCustomer(): ?User { return $this->customer; }
	public function getStatus(): string { return $this->status->value; }
	public function getStatusValue(): DemandStatus { return $this->status; }
	public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
	public function getVerifiedAt(): ?\DateTimeImmutable { return $this->verifiedAt; }
	public function getAccessTokenHash(): ?string { return $this->accessTokenHash; }
	public function getStatusLabel(): string
	{
		if ($this->matchingStopped && $this->status !== DemandStatus::Closed) { return 'Poskytovateľ vybraný'; }
		return match ($this->status->value) {
			self::PENDING => 'Čaká na overenie e-mailu',
			self::READY => 'Čaká na spracovanie',
			self::CLOSED => 'Uzavretý',
			self::REVIEW => 'Čaká na kontrolu zadania',
			self::NO_PROVIDERS => 'Zatiaľ nemáme vhodného poskytovateľa',
			self::INVITED => 'Čaká na reakcie poskytovateľov',
		};
	}
	public function issueAccess(?\DateTimeImmutable $now = null): string
	{
		if ($this->customer !== null) { throw new \InvalidArgumentException('Customer-owned demands do not use access tokens.'); }
		$now ??= new \DateTimeImmutable();
		$raw = bin2hex(random_bytes(32));
		$this->accessTokenHash = hash('sha256', $raw);
		$this->accessExpiresAt = $now->modify('+30 days');
		return $raw;
	}
	public function acceptsAccessHash(string $hash, ?\DateTimeImmutable $now = null): bool
	{
		return $this->customer === null && $this->accessTokenHash !== null && $this->accessExpiresAt !== null
			&& $this->accessExpiresAt > ($now ?? new \DateTimeImmutable())
			&& hash_equals($this->accessTokenHash, $hash);
	}
	public function isOwnedBy(User $user): bool
	{
		return $this->customer !== null && $this->customer->getId() === $user->getId();
	}
	public function verify(?\DateTimeImmutable $now = null): void
	{
		if ($this->status === DemandStatus::PendingVerification) {
			$this->verifiedAt = $now ?? new \DateTimeImmutable();
			$this->status = DemandStatus::Ready;
		}
	}
	public function close(?\DateTimeImmutable $now = null): void
	{
		if ($this->status !== DemandStatus::Closed) {
			$this->status = DemandStatus::Closed;
			$this->closedAt = $now ?? new \DateTimeImmutable();
			$this->leaseToken = null;
			$this->leaseUntil = null;
			$this->processAfter = null;
		}
	}

	public function claimProcessing(?\DateTimeImmutable $now = null): string
	{
		$now ??= new \DateTimeImmutable();
		if ($this->matchingStopped || $this->status !== DemandStatus::Ready || $this->verifiedAt === null
			|| ($this->processAfter !== null && $this->processAfter > $now)
			|| ($this->leaseUntil !== null && $this->leaseUntil > $now)) {
			throw new \InvalidArgumentException('Demand is not available for processing.');
		}
		$this->processingAttempts++;
		$this->leaseUntil = $now->modify('+5 minutes');
		return $this->leaseToken = bin2hex(random_bytes(16));
	}
	public function ownsLease(string $lease, ?\DateTimeImmutable $now = null): bool
	{
		return !$this->matchingStopped && $this->status === DemandStatus::Ready && $this->leaseToken === $lease && $this->leaseUntil !== null
			&& $this->leaseUntil > ($now ?? new \DateTimeImmutable());
	}
	public function scheduleRetry(string $note, ?string $summary = null, ?\DateTimeImmutable $now = null): void
	{
		if ($this->status !== DemandStatus::Ready && $this->status !== DemandStatus::Closed) {
			throw new \InvalidArgumentException('Only processing demands can be retried.');
		}
		$this->completeMatching(DemandStatus::Ready, $note, $summary, $now);
	}
	public function markNeedsReview(string $note, ?string $summary = null, ?\DateTimeImmutable $now = null): void
	{ $this->completeMatching(DemandStatus::NeedsReview, $note, $summary, $now); }
	public function markNoProviders(string $note, ?string $summary = null, ?\DateTimeImmutable $now = null): void
	{ $this->completeMatching(DemandStatus::NoProviders, $note, $summary, $now); }
	public function markProvidersInvited(string $note, ?string $summary = null, ?\DateTimeImmutable $now = null): void
	{ $this->completeMatching(DemandStatus::ProvidersInvited, $note, $summary, $now); }

	private function completeMatching(DemandStatus $status, string $note, ?string $summary, ?\DateTimeImmutable $now): void
	{
		if ($this->matchingStopped || $this->status === DemandStatus::Closed) { return; }
		if ($this->verifiedAt === null) { throw new \InvalidArgumentException('Only verified demands can be matched.'); }
		$this->status = $status;
		$this->processingNote = mb_substr($note, 0, 255);
		$this->serviceSummary = $summary ?? $this->serviceSummary;
		$this->leaseToken = null;
		$this->leaseUntil = null;
		$this->processAfter = ($now ?? new \DateTimeImmutable())->modify('+10 minutes');
	}
	public function getProcessingAttempts(): int { return $this->processingAttempts; }
	public function getProcessingNote(): ?string { return $this->processingNote; }
	public function getServiceSummary(): ?string { return $this->serviceSummary; }
	public function requeue(?CategoryService $category): void
	{
		if ($this->matchingStopped || $this->verifiedAt === null || $this->status === DemandStatus::Closed) {
			throw new \InvalidArgumentException('Only open verified demands can be requeued.');
		}
		$this->category = $category;
		$this->status = DemandStatus::Ready;
		$this->processingAttempts = 0;
		$this->processAfter = null;
		$this->leaseToken = null;
		$this->leaseUntil = null;
	}
}
