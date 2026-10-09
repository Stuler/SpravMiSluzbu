<?php declare(strict_types=1);
namespace App\Domain\Job;
use App\Domain\Demand\Demand;
use App\Domain\Provider\Provider;
use App\Model\Database\Entity\TId;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Table(name: 'job')]
class Job
{
    use TId;
    public const WAITING = 'waiting', CONFIRMED = 'confirmed', REPORTED = 'reported', CANCELLED = 'cancelled';
    public const UNVERIFIED = 'unverified', PROVIDER_VERIFIED = 'provider_confirmed', ADMIN_VERIFIED = 'admin_verified', DISPUTED = 'disputed';
    #[ORM\Column(type: 'string', length: 24)] private string $status = self::WAITING;
    #[ORM\Column(type: 'string', length: 24)] private string $verification = self::UNVERIFIED;
    #[ORM\Column(type: 'datetime_immutable')] private \DateTimeImmutable $selectedAt;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)] private ?\DateTimeImmutable $confirmedAt = null;
    #[ORM\Column(type: 'date_immutable', nullable: true)] private ?\DateTimeImmutable $performedOn = null;
    #[ORM\Column(type: 'string', length: 16, nullable: true)] private ?string $outcome = null;
    #[ORM\Column(type: 'string', length: 1000, nullable: true)] private ?string $description = null;
    #[ORM\Column(type: 'string', length: 1000, nullable: true)] private ?string $disputeReason = null;
    #[ORM\ManyToOne(targetEntity: Demand::class)] #[ORM\JoinColumn(name: 'active_demand_id', nullable: true, unique: true)] private ?Demand $activeDemand;
    public function __construct(
        #[ORM\ManyToOne(targetEntity: Demand::class)] #[ORM\JoinColumn(nullable: false)] private Demand $demand,
        #[ORM\ManyToOne(targetEntity: Provider::class)] #[ORM\JoinColumn(nullable: false)] private Provider $provider,
    ) { $this->activeDemand = $demand; $this->selectedAt = new \DateTimeImmutable(); }
    public function getDemand(): Demand { return $this->demand; }
    public function getProvider(): Provider { return $this->provider; }
    public function getStatus(): string { return $this->status; }
    public function getVerification(): string { return $this->verification; }
    public function getSelectedAt(): \DateTimeImmutable { return $this->selectedAt; }
    public function getConfirmedAt(): ?\DateTimeImmutable { return $this->confirmedAt; }
    public function getPerformedOn(): ?\DateTimeImmutable { return $this->performedOn; }
    public function getOutcome(): ?string { return $this->outcome; }
    public function getDescription(): ?string { return $this->description; }
    public function getDisputeReason(): ?string { return $this->disputeReason; }
    public function isReviewable(): bool { return $this->status === self::REPORTED && in_array($this->outcome, ['completed', 'partial'], true); }
    public function isVerified(): bool { return in_array($this->verification, [self::PROVIDER_VERIFIED, self::ADMIN_VERIFIED], true); }
    public function confirm(): void
    {
        if ($this->status === self::CONFIRMED) { return; }
        if ($this->status !== self::WAITING || $this->verification === self::DISPUTED) { throw new \InvalidArgumentException('Agreement cannot be confirmed in this state.'); }
        $this->status = self::CONFIRMED; $this->verification = self::PROVIDER_VERIFIED; $this->confirmedAt = new \DateTimeImmutable();
    }
    public function cancel(): void
    {
        if ($this->status === self::CANCELLED) { return; }
        if ($this->status === self::REPORTED) { throw new \InvalidArgumentException('Reported work cannot be cancelled.'); }
        $this->status = self::CANCELLED; $this->activeDemand = null;
    }
    public function report(string $outcome, ?\DateTimeImmutable $date, string $description): void
    {
        if (!in_array($outcome, ['completed', 'partial', 'ongoing', 'not_started'], true) || !in_array($this->status, [self::WAITING, self::CONFIRMED], true)) { throw new \InvalidArgumentException('Invalid work outcome or job state.'); }
        if ($outcome === 'ongoing') { $this->outcome = $outcome; return; }
        if ($outcome === 'not_started') { $this->cancel(); $this->outcome = $outcome; return; }
        $description = trim($description);
        if ($date === null || $date->format('Y-m-d') > (new \DateTimeImmutable('today'))->format('Y-m-d') || $description === '' || mb_strlen($description) > 1000) { throw new \InvalidArgumentException('A past or current date and short description are required.'); }
        $this->performedOn = $date->setTime(0, 0); $this->description = $description; $this->outcome = $outcome; $this->status = self::REPORTED;
    }
    public function dispute(string $reason): void
    {
        $reason = trim($reason);
        if ($this->status === self::CANCELLED || $reason === '' || mb_strlen($reason) > 1000) { throw new \InvalidArgumentException('Invalid collaboration dispute.'); }
        $this->verification = self::DISPUTED; $this->disputeReason = $reason;
    }
    public function rejectVerification(): void { $this->verification = self::UNVERIFIED; }
    public function verifyByAdmin(): void { $this->verification = self::ADMIN_VERIFIED; }
    public function getStatusLabel(): string { return match ($this->status) { self::WAITING => 'Čaká na potvrdenie dohody', self::CONFIRMED => 'Dohoda potvrdená', self::REPORTED => 'Zákazník oznámil vykonanie', default => 'Zrušená' }; }
    public function getOutcomeLabel(): string { return match ($this->outcome) { 'completed' => 'Práce vykonané', 'partial' => 'Práce vykonané čiastočne', 'ongoing' => 'Práce ešte prebiehajú', 'not_started' => 'Práce sa nezačali', default => 'Vykonanie zatiaľ neoznámené' }; }
    public function getVerificationLabel(): string { return match ($this->verification) { self::PROVIDER_VERIFIED => 'Poskytovateľ vopred potvrdil dohodu', self::ADMIN_VERIFIED => 'Spoluprácu overil administrátor', self::DISPUTED => 'Spolupráca sa preveruje', default => 'Spolupráca zatiaľ neoverená' }; }
}
