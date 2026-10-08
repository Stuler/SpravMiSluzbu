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

	#[ORM\Column(type: 'string', length: 32)]
	private string $status = self::PENDING;
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
	public function getStatus(): string { return $this->status; }
	public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
	public function getVerifiedAt(): ?\DateTimeImmutable { return $this->verifiedAt; }
	public function getAccessTokenHash(): ?string { return $this->accessTokenHash; }
	public function getStatusLabel(): string
	{
		return match ($this->status) {
			self::PENDING => 'Čaká na overenie e-mailu',
			self::READY => 'Čaká na spracovanie',
			self::CLOSED => 'Uzavretý',
			self::REVIEW => 'Čaká na kontrolu zadania',
			self::NO_PROVIDERS => 'Zatiaľ nemáme vhodného poskytovateľa',
			self::INVITED => 'Čaká na reakcie poskytovateľov',
			default => 'Spracúva sa',
		};
	}
	public function issueAccess(): string
	{
		$raw = bin2hex(random_bytes(32));
		$this->accessTokenHash = hash('sha256', $raw);
		$this->accessExpiresAt = new \DateTimeImmutable('+30 days');
		return $raw;
	}
	public function acceptsAccessHash(string $hash): bool
	{
		return $this->customer === null && $this->accessTokenHash !== null && $this->accessExpiresAt > new \DateTimeImmutable()
			&& hash_equals($this->accessTokenHash, $hash);
	}
	public function isOwnedBy(User $user): bool
	{
		return $this->customer !== null && $this->customer->getId() === $user->getId();
	}
	public function verify(): void
	{
		if ($this->status === self::PENDING) {
			$this->verifiedAt = new \DateTimeImmutable();
			$this->status = self::READY;
		}
	}
	public function close(): void
	{
		if ($this->status !== self::CLOSED) {
			$this->status = self::CLOSED;
			$this->closedAt = new \DateTimeImmutable();
		}
	}

	public function claimProcessing(): string
	{
		$this->processingAttempts++;
		$this->leaseUntil = new \DateTimeImmutable('+5 minutes');
		return $this->leaseToken = bin2hex(random_bytes(16));
	}
	public function ownsLease(string $lease): bool
	{
		return $this->status === self::READY && $this->leaseToken === $lease && $this->leaseUntil > new \DateTimeImmutable();
	}
	public function finishProcessing(string $status, string $note, ?string $summary = null): void
	{
		if ($this->status === self::CLOSED) { return; }
		$this->status = $status;
		$this->processingNote = mb_substr($note, 0, 255);
		$this->serviceSummary = $summary ?? $this->serviceSummary;
		$this->leaseToken = null;
		$this->leaseUntil = null;
		$this->processAfter = new \DateTimeImmutable('+10 minutes');
	}
	public function getProcessingAttempts(): int { return $this->processingAttempts; }
	public function getProcessingNote(): ?string { return $this->processingNote; }
	public function getServiceSummary(): ?string { return $this->serviceSummary; }
	public function requeue(?CategoryService $category): void
	{
		if ($this->verifiedAt === null || $this->status === self::CLOSED) {
			throw new \InvalidArgumentException('Only open verified demands can be requeued.');
		}
		$this->category = $category;
		$this->status = self::READY;
		$this->processingAttempts = 0;
		$this->processAfter = null;
		$this->leaseToken = null;
		$this->leaseUntil = null;
	}
}
