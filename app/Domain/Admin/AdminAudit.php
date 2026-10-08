<?php declare(strict_types=1);
namespace App\Domain\Admin;

use App\Domain\User\User;
use App\Model\Database\Entity\TId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'admin_audit', indexes: [new ORM\Index(name: 'IDX_ADMIN_AUDIT_TARGET', columns: ['target_type', 'target_id', 'created_at'])])]
class AdminAudit
{
    use TId;
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;
    public function __construct(
        #[ORM\ManyToOne(targetEntity: User::class)] #[ORM\JoinColumn(nullable: false)] private User $actor,
        #[ORM\Column(type: 'string', length: 32)] private string $targetType,
        #[ORM\Column(type: 'integer')] private int $targetId,
        #[ORM\Column(type: 'string', length: 64)] private string $operation,
        #[ORM\Column(type: 'json')] private array $changes = [],
    ) { $this->createdAt = new \DateTimeImmutable(); }
    public function getActor(): User { return $this->actor; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getOperation(): string { return $this->operation; }
    public function getChanges(): array { return $this->changes; }
    public function getOperationLabel(): string
    {
        return match ($this->operation) {
            'blocked' => 'Účet zablokovaný', 'unblocked' => 'Účet odblokovaný',
            'profile_updated' => 'Profil upravený', 'coverage_updated' => 'Služby a regióny upravené',
            'activation_requested' => 'Vyžiadané odoslanie aktivácie', 'reset_requested' => 'Vyžiadaná obnova hesla',
            'category_saved' => 'Kategória uložená', 'category_retired' => 'Kategória a potomkovia vyradení',
            'demand_requeued' => 'Dopyt zaradený na spracovanie', 'providers_approved' => 'Výber firiem potvrdený',
            'delivery_retried' => 'Vyžiadané opakovanie e-mailu', default => $this->operation,
        };
    }
}
