<?php declare(strict_types=1);

namespace App\Domain\Matching;

use App\Domain\Demand\Demand;
use App\Model\Database\Entity\TId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'ai_run')]
class AiRun
{
	use TId;
	#[ORM\Column(type: 'datetime_immutable')]
	private \DateTimeImmutable $createdAt;
	/** @param array<string, mixed> $input @param array<string, mixed> $output */
	public function __construct(
		#[ORM\ManyToOne(targetEntity: Demand::class)] #[ORM\JoinColumn(nullable: false)] private Demand $demand,
		#[ORM\Column(type: 'string', length: 80)] private string $promptVersion,
		#[ORM\Column(type: 'string', length: 100)] private string $model,
		#[ORM\Column(type: 'json')] private array $input,
		#[ORM\Column(type: 'json')] private array $output,
		#[ORM\Column(type: 'integer')] private int $inputTokens,
		#[ORM\Column(type: 'integer')] private int $outputTokens,
		#[ORM\Column(type: 'integer')] private int $durationMs,
	) { $this->createdAt = new \DateTimeImmutable(); }
	public function getPromptVersion(): string { return $this->promptVersion; }
	public function getModel(): string { return $this->model; }
	public function getInput(): array { return $this->input; }
	public function getOutput(): array { return $this->output; }
	public function getInputTokens(): int { return $this->inputTokens; }
	public function getOutputTokens(): int { return $this->outputTokens; }
	public function getDurationMs(): int { return $this->durationMs; }
	public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
