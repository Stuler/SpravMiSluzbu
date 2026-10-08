<?php declare(strict_types=1);

namespace App\Domain\Customer;

use App\Domain\User\User;
use App\Model\Database\Entity\TId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'customer_token')]
class CustomerToken
{
	use TId;
	public const ACTIVATE = 'activate';
	public const RESET = 'reset';

	#[ORM\Column(type: 'datetime_immutable', nullable: true)]
	private ?\DateTimeImmutable $usedAt = null;

	public function __construct(
		#[ORM\ManyToOne(targetEntity: User::class)]
		#[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
		private User $user,
		#[ORM\Column(type: 'string', length: 16)]
		private string $purpose,
		#[ORM\Column(type: 'string', length: 64, unique: true)]
		private string $tokenHash,
		#[ORM\Column(type: 'datetime_immutable')]
		private \DateTimeImmutable $expiresAt,
	) {}

	public function getUser(): User { return $this->user; }
	public function isValid(string $purpose): bool
	{
		return $this->purpose === $purpose && $this->usedAt === null && $this->expiresAt > new \DateTimeImmutable();
	}
	public function consume(): void { $this->usedAt = new \DateTimeImmutable(); }
}
