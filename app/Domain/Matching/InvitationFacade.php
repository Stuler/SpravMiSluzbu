<?php declare(strict_types=1);

namespace App\Domain\Matching;

use App\Domain\Demand\Demand;
use App\Domain\Provider\Provider;
use App\Model\Database\EntityManagerDecorator;
use App\Model\Mail\MailSender;
use Doctrine\DBAL\LockMode;
use Nette\Application\LinkGenerator;

final class InvitationFacade
{
	public function __construct(private EntityManagerDecorator $em, private MailSender $mail, private LinkGenerator $links) {}
	public function activeProvider(int $id, ?int $sessionVersion = null): ?Provider
	{
		$p = $this->em->find(Provider::class, $id);
		return $p instanceof Provider && $p->isActivated() && $p->getDateDeleted() === null
			&& ($sessionVersion === null || $sessionVersion === $p->getSessionVersion()) ? $p : null;
	}
	public function forProvider(Provider $provider): array
	{
		return $this->em->getRepository(ProviderInvitation::class)->findBy(['provider' => $provider], ['createdAt' => 'DESC'], 100);
	}
	public function forDemand(Demand $demand): array
	{
		return $this->em->getRepository(ProviderInvitation::class)->findBy(['demand' => $demand], ['id' => 'ASC']);
	}
	public function findForProvider(int $id, Provider $provider): ?ProviderInvitation
	{
		$invitation = $this->em->find(ProviderInvitation::class, $id);
		return $invitation instanceof ProviderInvitation && $invitation->getProvider()->getId() === $provider->getId()
			&& $this->activeProvider($provider->getId()) !== null ? $invitation : null;
	}
	public function respond(int $id, Provider $provider, bool $accept): void
	{
		$invitation = $this->findForProvider($id, $provider);
		if ($invitation === null || $invitation->getDemand()->getStatus() === Demand::CLOSED) { throw new \InvalidArgumentException('Invitation access denied.'); }
		$answered = $this->em->wrapInTransaction(function () use ($invitation, $provider, $accept): bool {
			$this->em->refresh($invitation->getDemand(), LockMode::PESSIMISTIC_WRITE);
			$this->em->refresh($invitation, LockMode::PESSIMISTIC_WRITE);
			$this->em->refresh($provider);
			if ($this->activeProvider($provider->getId()) === null || $invitation->getDemand()->getStatus() === Demand::CLOSED) {
				return false;
			}
			$invitation->answer($accept);
			return true;
		});
		if (!$answered) { throw new \InvalidArgumentException('Invitation is no longer available.'); }
	}
	public function updateProfile(Provider $provider, bool $accepts, string $description): void
	{
		if ($this->activeProvider($provider->getId()) === null || mb_strlen($description) > 2000) {
			throw new \InvalidArgumentException('Invalid provider profile.');
		}
		$provider->setAcceptsDemands($accepts);
		$provider->setServiceDescription(trim($description) ?: null);
		$this->em->flush();
	}
	public function deliverOne(): bool
	{
		$invitation = $this->em->wrapInTransaction(function (): ?ProviderInvitation {
			$i = $this->em->createQueryBuilder()->select('i')->from(ProviderInvitation::class, 'i')->where('i.delivery = :pending')
				->setParameter('pending', 'pending')->orderBy('i.id', 'ASC')->setMaxResults(1)->getQuery()->setLockMode(LockMode::PESSIMISTIC_WRITE)->getOneOrNullResult();
			if (!$i instanceof ProviderInvitation) { return null; }
			$this->em->refresh($i);
			$i->startDelivery();
			return $i;
		});
		if ($invitation === null) { return false; }
		$this->em->refresh($invitation->getDemand());
		$this->em->refresh($invitation->getProvider());
		$provider = $invitation->getProvider();
		if ($invitation->getDemand()->getStatus() === Demand::CLOSED || $this->activeProvider($provider->getId()) === null || !$provider->acceptsDemands()) {
			$invitation->finishDelivery('cancelled');
		} else {
			try {
				$this->mail->sendCustomerLink($provider->getEmail(), 'Nový dopyt na službu na Bripeone',
					'Vo vašej oblasti je nový dopyt. Po prihlásení si pozrite zadanie a potvrďte záujem.',
					$this->links->link('Front:ProviderDemand:detail', ['id' => $invitation->getId()]));
				$invitation->finishDelivery('sent');
			} catch (\Throwable $e) {
				// SMTP failures may occur after delivery. Do not silently resend an ambiguous message.
				$invitation->finishDelivery('unknown');
			}
		}
		$this->em->flush();
		return true;
	}
	public function retryDelivery(int $id, Demand $demand): void
	{
		$i = $this->em->find(ProviderInvitation::class, $id);
		if (!$i instanceof ProviderInvitation || $i->getDemand()->getId() !== $demand->getId() || $demand->getStatus() === Demand::CLOSED) {
			throw new \InvalidArgumentException('Invalid invitation.');
		}
		$retried = $this->em->wrapInTransaction(function () use ($i, $demand): bool {
			$this->em->refresh($demand, LockMode::PESSIMISTIC_WRITE);
			$this->em->refresh($i, LockMode::PESSIMISTIC_WRITE);
			if ($demand->getStatus() === Demand::CLOSED || !$i->canRetryDelivery()) { return false; }
			$i->retryDelivery();
			return true;
		});
		if (!$retried) { throw new \InvalidArgumentException('Delivery cannot be retried.'); }
	}
}
