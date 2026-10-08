<?php declare(strict_types=1);

namespace App\Domain\Customer;

use App\Domain\LoginRole\LoginRole;
use App\Domain\StateUser\StateUser;
use App\Domain\StateUser\StateUserRepository;
use App\Domain\User\User;
use App\Model\Database\EntityManagerDecorator;
use App\Model\Mail\MailSender;
use App\Model\Security\Passwords;
use App\Model\Utils\Emails;
use Doctrine\DBAL\LockMode;
use Nette\Application\LinkGenerator;
use Nette\Utils\Validators;

final class CustomerAccountFacade
{
	public function __construct(
		private EntityManagerDecorator $em,
		private MailSender $mail,
		private LinkGenerator $links,
	) {}

	public function register(string $name, string $email, string $password): void
	{
		$name = trim($name);
		$email = Emails::normalize($email);
		if ($name === '' || mb_strlen($name) > 255 || !Validators::isEmail($email) || strlen($email) > 100) {
			throw new \InvalidArgumentException('Invalid customer details.');
		}
		$this->validatePassword($password);
		if ($this->em->getRepository(User::class)->findOneBy(['email' => $email]) !== null) {
			return;
		}
		// Public registration never accepts a role from the request.
		$role = $this->em->getRepository(LoginRole::class)->findOneBy(['name' => LoginRole::ROLE_MEMBER]);
		$state = $this->em->find(StateUser::class, StateUserRepository::STATE_FRESH);
		if ($role === null || $state === null) {
			throw new \LogicException('Customer account states are not configured.');
		}
		$user = new User(name: $name, surname: '', email: $email,
			password: Passwords::create()->hash($password), loginRole: $role, stateUser: $state,
			streetNo: null, city: null, zipCode: null, hash: '');
		$raw = bin2hex(random_bytes(32));
		$token = new CustomerToken($user, CustomerToken::ACTIVATE, hash('sha256', $raw), new \DateTimeImmutable('+24 hours'));
		$this->em->wrapInTransaction(function () use ($user, $token): void {
			$this->em->persist($user);
			$this->em->persist($token);
		});
		$this->em->afterCommit(function () use ($user, $raw): void { $this->sendToken($user, CustomerToken::ACTIVATE, $raw); });
	}

	public function requestToken(string $email, string $purpose): void
	{
		if (!in_array($purpose, [CustomerToken::ACTIVATE, CustomerToken::RESET], true)) {
			throw new \InvalidArgumentException('Invalid token purpose.');
		}
		$user = $this->em->getRepository(User::class)->findOneBy(['email' => Emails::normalize($email), 'dateDeleted' => null]);
		if (!$user instanceof User || $user->isBlocked() || $user->getLoginRole()->getName() !== LoginRole::ROLE_MEMBER) {
			return;
		}
		if ($purpose === CustomerToken::ACTIVATE && $user->getStateUser()->getId() !== StateUserRepository::STATE_FRESH) {
			return;
		}
		if ($purpose === CustomerToken::RESET && !$user->isActivated()) {
			return;
		}
		$raw = bin2hex(random_bytes(32));
		$issued = $this->em->wrapInTransaction(function () use ($user, $purpose, $raw): bool {
			$this->em->refresh($user, LockMode::PESSIMISTIC_WRITE);
			$requiredState = $purpose === CustomerToken::RESET ? StateUserRepository::STATE_ACTIVATED : StateUserRepository::STATE_FRESH;
			if ($user->isBlocked() || $user->getDateDeleted() !== null || $user->getLoginRole()->getName() !== LoginRole::ROLE_MEMBER
				|| $user->getStateUser()->getId() !== $requiredState) { return false; }
			$this->invalidateTokens($user, $purpose);
			$this->em->persist(new CustomerToken($user, $purpose, hash('sha256', $raw),
				new \DateTimeImmutable($purpose === CustomerToken::RESET ? '+1 hour' : '+24 hours')));
			return true;
		});
		if ($issued) {
			$this->em->afterCommit(function () use ($user, $purpose, $raw): void { $this->sendToken($user, $purpose, $raw); });
		}
	}

	public function isValidToken(string $raw, string $purpose): bool
	{
		return $this->findToken($raw, $purpose) !== null;
	}

	public function activate(string $raw): void
	{
		$this->useToken($raw, CustomerToken::ACTIVATE, function (User $user): void {
			$state = $this->em->find(StateUser::class, StateUserRepository::STATE_ACTIVATED);
			if (!$state instanceof StateUser) {
				throw new \LogicException('Customer active state is not configured.');
			}
			$user->setStateUser($state);
			$user->setDateActivated();
		});
	}

	public function resetPassword(string $raw, string $password): void
	{
		$this->validatePassword($password);
		$this->useToken($raw, CustomerToken::RESET, function (User $user) use ($password): void {
			$user->setPassword(Passwords::create()->hash($password));
			$user->revokeCustomerSessions();
		});
	}

	public function findActiveCustomer(int $id, ?int $sessionVersion = null): ?User
	{
		$user = $this->em->find(User::class, $id);
		return $user instanceof User && $user->getDateDeleted() === null && $user->isActivated()
			&& $user->getLoginRole()->getName() === LoginRole::ROLE_MEMBER
			&& ($sessionVersion === null || $sessionVersion === $user->getCustomerSessionVersion()) ? $user : null;
	}

	private function findToken(string $raw, string $purpose): ?CustomerToken
	{
		if (!preg_match('/^[a-f0-9]{64}$/D', $raw)) {
			return null;
		}
		$token = $this->em->getRepository(CustomerToken::class)->findOneBy(['tokenHash' => hash('sha256', $raw)]);
		if (!$token instanceof CustomerToken || !$token->isValid($purpose)) {
			return null;
		}
		$user = $token->getUser();
		$state = $purpose === CustomerToken::ACTIVATE ? StateUserRepository::STATE_FRESH : StateUserRepository::STATE_ACTIVATED;
		return !$user->isBlocked() && $user->getDateDeleted() === null && $user->getLoginRole()->getName() === LoginRole::ROLE_MEMBER
			&& $user->getStateUser()->getId() === $state ? $token : null;
	}

	private function useToken(string $raw, string $purpose, callable $operation): void
	{
		if ($this->findToken($raw, $purpose) === null) {
			throw new \InvalidArgumentException('Invalid or expired customer token.');
		}
		$this->em->wrapInTransaction(function () use ($raw, $purpose, $operation): void {
			$token = $this->findToken($raw, $purpose);
			if ($token === null) {
				throw new \InvalidArgumentException('Invalid or expired customer token.');
			}
			$this->em->refresh($token->getUser(), LockMode::PESSIMISTIC_WRITE);
			$this->em->refresh($token, LockMode::PESSIMISTIC_WRITE);
			if ($this->findToken($raw, $purpose) === null) {
				throw new \InvalidArgumentException('Invalid or expired customer token.');
			}
			$operation($token->getUser());
			$this->invalidateTokens($token->getUser(), $purpose);
		});
	}

	private function invalidateTokens(User $user, string $purpose): void
	{
		foreach ($this->em->getRepository(CustomerToken::class)->findBy(['user' => $user, 'purpose' => $purpose, 'usedAt' => null]) as $token) {
			$token->consume();
		}
	}

	private function validatePassword(string $password): void
	{
		if (mb_strlen($password) < 10 || strlen($password) > 72) {
			throw new \InvalidArgumentException('Password must contain at least 10 characters and at most 72 bytes.');
		}
	}

	private function sendToken(User $user, string $purpose, string $raw): void
	{
		$link = $this->links->link($purpose === CustomerToken::ACTIVATE ? 'Front:UserSign:activateUser' : 'Front:UserSign:reset', ['token' => $raw]);
		$this->mail->sendCustomerLink($user->getEmail(), $purpose === CustomerToken::ACTIVATE ? 'Aktivácia zákazníckeho účtu' : 'Obnova hesla',
			$purpose === CustomerToken::ACTIVATE ? 'Potvrďte svoj e-mail a aktivujte si účet. Odkaz platí 24 hodín.' : 'Nastavte si nové heslo. Odkaz platí jednu hodinu.', $link);
	}
}
