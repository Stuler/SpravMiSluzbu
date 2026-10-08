<?php declare(strict_types=1);

namespace App\Domain\Matching;

use App\Domain\Demand\Demand;
use App\Domain\Provider\Provider;
use App\Model\Database\EntityManagerDecorator;
use App\Model\Mail\MailSender;
use Nette\Application\LinkGenerator;

final class InvitationFacade
{
	public function __construct(private EntityManagerDecorator $em, private MailSender $mail, private LinkGenerator $links,
		private InvitationRepository $invitationRepository, private \App\Domain\Demand\DemandRepository $demands,
		private \App\Domain\Provider\ProviderRepository $providers) {}
	public function activeProvider(int $id, ?int $sessionVersion = null): ?Provider
	{
		$p = $this->providers->find($id);
		return $p instanceof Provider && $p->isActivated() && $p->getDateDeleted() === null
			&& ($sessionVersion === null || $sessionVersion === $p->getSessionVersion()) ? $p : null;
	}
	public function forProvider(Provider $provider): array
	{
		return $this->invitationRepository->forProvider($provider);
	}
	public function forDemand(Demand $demand): array
	{
		return $this->invitationRepository->forDemand($demand);
	}
	public function findForProvider(int $id, Provider $provider): ?ProviderInvitation
	{
		$invitation = $this->invitationRepository->find($id);
		return $invitation instanceof ProviderInvitation && $invitation->getProvider()->getId() === $provider->getId()
			&& $this->activeProvider($provider->getId()) !== null ? $invitation : null;
	}
	public function respond(int $id, Provider $provider, bool $accept): void
	{
		$invitation = $this->findForProvider($id, $provider);
		if ($invitation === null || $invitation->getDemand()->getStatus() === Demand::CLOSED) { throw new \InvalidArgumentException('Invitation access denied.'); }
		$answered = $this->em->wrapInTransaction(function () use ($invitation, $provider, $accept): bool {
			$this->demands->refreshForUpdate($invitation->getDemand());
			$this->invitationRepository->refreshForUpdate($invitation);
			$this->providers->refreshForUpdate($provider);
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
		$this->em->wrapInTransaction(function () use ($provider, $accepts, $description): void {
			$this->providers->refreshForUpdate($provider);
			if ($this->activeProvider($provider->getId()) === null || mb_strlen($description) > 2000) {
				throw new \InvalidArgumentException('Invalid provider profile.');
			}
			$provider->setServiceDescription($description);
			$provider->setAcceptsDemands($accepts);
		});
	}
	public function deliverOne(): bool
	{
		$invitation = $this->em->wrapInTransaction(function (): ?ProviderInvitation {
			$i = $this->invitationRepository->nextPendingForUpdate();
			if ($i === null) { return null; }
			$i->startDelivery();
			return $i;
		});
		if ($invitation === null) { return false; }
		$this->em->refresh($invitation->getDemand());
		$this->em->refresh($invitation->getProvider());
		$provider = $invitation->getProvider();
		if ($invitation->getDemand()->getStatus() === Demand::CLOSED || $this->activeProvider($provider->getId()) === null || !$provider->acceptsDemands()) {
			$invitation->cancelDelivery();
		} else {
			try {
				$this->mail->sendCustomerLink($provider->getEmail(), 'Nový dopyt na službu na Bripeone',
					'Vo vašej oblasti je nový dopyt. Po prihlásení si pozrite zadanie a potvrďte záujem.',
					$this->links->link('Front:ProviderDemand:detail', ['id' => $invitation->getId()]));
				$invitation->markDelivered();
			} catch (\Throwable $e) {
				// SMTP failures may occur after delivery. Do not silently resend an ambiguous message.
				$invitation->markDeliveryUnknown();
			}
		}
		$this->em->flush();
		return true;
	}
	public function retryDelivery(int $id, Demand $demand): void
	{
		$i = $this->invitationRepository->find($id);
		if (!$i instanceof ProviderInvitation || $i->getDemand()->getId() !== $demand->getId() || $demand->getStatus() === Demand::CLOSED) {
			throw new \InvalidArgumentException('Invalid invitation.');
		}
		$retried = $this->em->wrapInTransaction(function () use ($i, $demand): bool {
			$this->demands->refreshForUpdate($demand);
			$this->invitationRepository->refreshForUpdate($i);
			if ($demand->getStatus() === Demand::CLOSED || !$i->canRetryDelivery()) { return false; }
			$i->retryDelivery();
			return true;
		});
		if (!$retried) { throw new \InvalidArgumentException('Delivery cannot be retried.'); }
	}
}
