<?php declare(strict_types=1);
namespace App\Domain\Review;
use App\Domain\Job\Job;
use App\Domain\User\User;
use App\Model\Database\Entity\TId;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Table(name: 'review')]
class Review
{
    use TId;
    public const PENDING = 'pending', PUBLISHED = 'published', REJECTED = 'rejected';
    #[ORM\Column(type: 'string', length: 16)] private string $status = self::PENDING;
    #[ORM\Column(type: 'datetime_immutable')] private \DateTimeImmutable $createdAt;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)] private ?\DateTimeImmutable $publishedAt = null;
    #[ORM\Column(type: 'string', length: 1000, nullable: true)] private ?string $decisionReason = null;
    #[ORM\ManyToOne(targetEntity: User::class)] #[ORM\JoinColumn(nullable: true)] private ?User $decidedBy = null;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)] private ?\DateTimeImmutable $decidedAt = null;
    #[ORM\Column(type: 'string', length: 2000, nullable: true)] private ?string $reply = null;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)] private ?\DateTimeImmutable $repliedAt = null;
    public function __construct(
        #[ORM\OneToOne(targetEntity: Job::class)] #[ORM\JoinColumn(nullable: false, unique: true)] private Job $job,
        #[ORM\Column(type: 'smallint')] private int $rating,
        #[ORM\Column(type: 'string', length: 3000)] private string $text,
    ) {
        $this->text = trim($text);
        if (!$job->isReviewable() || $rating < 1 || $rating > 5 || $this->text === '' || mb_strlen($this->text) > 3000) { throw new \InvalidArgumentException('Invalid review.'); }
        $this->createdAt = new \DateTimeImmutable();
        if ($job->isVerified()) { $this->publish(); }
    }
    public function getJob(): Job { return $this->job; }
    public function getRating(): int { return $this->rating; }
    public function getText(): string { return $this->text; }
    public function getStatus(): string { return $this->status; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getPublishedAt(): ?\DateTimeImmutable { return $this->publishedAt; }
    public function getDecisionReason(): ?string { return $this->decisionReason; }
    public function getDecidedBy(): ?User { return $this->decidedBy; }
    public function getDecidedAt(): ?\DateTimeImmutable { return $this->decidedAt; }
    public function getReply(): ?string { return $this->reply; }
    public function publish(): void
    {
        if (!$this->job->isReviewable() || !$this->job->isVerified()) { throw new \InvalidArgumentException('Unverified review cannot be published.'); }
        $this->status = self::PUBLISHED; $this->publishedAt ??= new \DateTimeImmutable();
    }
    public function awaitVerification(): void { if ($this->status === self::PUBLISHED) { $this->status = self::PENDING; } }
    public function decide(bool $publish, string $reason, User $admin): void
    {
        $reason = trim($reason);
        if ($this->status !== self::PENDING || $reason === '' || mb_strlen($reason) > 1000) { throw new \InvalidArgumentException('A pending review and decision reason are required.'); }
        if ($publish) { $this->job->verifyByAdmin(); $this->publish(); } else { $this->job->rejectVerification(); $this->status = self::REJECTED; }
        $this->decisionReason = $reason; $this->decidedBy = $admin; $this->decidedAt = new \DateTimeImmutable();
    }
    public function reply(string $text): void
    {
        $text = trim($text);
        if ($this->reply !== null || $this->status !== self::PUBLISHED || $text === '' || mb_strlen($text) > 2000) { throw new \InvalidArgumentException('Only one reply to a published review is allowed.'); }
        $this->reply = $text; $this->repliedAt = new \DateTimeImmutable();
    }
    public function getStatusLabel(): string { return match ($this->status) { self::PUBLISHED => 'Zverejnená', self::REJECTED => 'Zamietnutá', default => 'Čaká na preverenie' }; }
}
