<?php declare(strict_types=1);
namespace App\Domain\Admin;

use App\Domain\LoginRole\LoginRole;
use App\Domain\User\User;
use App\Model\Database\EntityManagerDecorator;
use App\Model\Security\Identity;
use Nette\Security\User as SecurityUser;

final class AdminAuditFacade
{
    public function __construct(private EntityManagerDecorator $em, private SecurityUser $security) {}
    public function requireAdmin(): User
    {
        $identity = $this->security->getIdentity();
        if (!$this->security->isLoggedIn() || !$this->security->isAllowed('Admin:Home')
            || !$identity instanceof Identity || $identity->isProvider()) {
            throw new \Nette\Application\ForbiddenRequestException('Administration access denied.');
        }
        $actor = $this->em->find(User::class, $identity->getId());
        if (!$actor instanceof User || !$actor->isActivated() || $actor->getDateDeleted() !== null
            || $actor->getLoginRole()->getName() !== LoginRole::ROLE_ADMIN) {
            throw new \Nette\Application\ForbiddenRequestException('Administrator account is not active.');
        }
        return $actor;
    }
    /** Only explicit change metadata belongs here, never forms, passwords or tokens. */
    public function record(string $type, int $id, string $operation, array $changes = []): void
    {
        $this->em->persist(new AdminAudit($this->requireAdmin(), $type, $id, $operation, $changes));
    }
    public function history(string $type, int $id, int $page = 1): array
    {
        $this->requireAdmin();
        return $this->em->getRepository(AdminAudit::class)->findBy(['targetType' => $type, 'targetId' => $id], ['id' => 'DESC'], 21, (max(1, $page) - 1) * 20);
    }
}
