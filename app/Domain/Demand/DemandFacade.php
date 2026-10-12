<?php declare(strict_types=1);

namespace App\Domain\Demand;

use App\Domain\CategoryService\CategoryCatalogue;
use App\Domain\CategoryService\CategoryService;
use App\Domain\City\City;
use App\Domain\Customer\CustomerAccountFacade;
use App\Domain\User\User;
use App\Model\Database\EntityManagerDecorator;
use App\Model\Mail\MailSender;
use App\Model\Utils\Emails;
use Nette\Application\LinkGenerator;
use Nette\Utils\Validators;

final class DemandFacade
{
	public function __construct(
		private EntityManagerDecorator $em,
		private CustomerAccountFacade $accounts,
		private MailSender $mail,
		private LinkGenerator $links,
		private DemandRepository $demands,
	) {}

	/** @param array<string, mixed> $data */
	public function create(array $data, ?User $customer, string $submissionKey): Demand
	{
		if (!preg_match('/^[a-f0-9]{64}$/D', $submissionKey)) {
			throw new \InvalidArgumentException('Invalid submission key.');
		}
		$existing = $this->demands->findBySubmissionKey($submissionKey);
		if ($existing instanceof Demand) {
			return $existing;
		}
		if ($customer !== null && $this->accounts->findActiveCustomer($customer->getId()) === null) {
			throw new \InvalidArgumentException('Customer account is not active.');
		}
		$description = trim((string) ($data['description'] ?? ''));
		$name = trim((string) ($data['contactName'] ?? ''));
		$term = trim((string) ($data['requestedTerm'] ?? ''));
		$email = $customer !== null ? $customer->getEmail() : Emails::normalize((string) ($data['contactEmail'] ?? ''));
		$phone = trim((string) ($data['contactPhone'] ?? ''));
		if (mb_strlen($description) < 15 || mb_strlen($description) > 5000 || $name === '' || mb_strlen($name) > 255
			|| $term === '' || mb_strlen($term) > 255 || !Validators::isEmail($email) || strlen($email) > 100
			|| ($phone !== '' && !preg_match('/^[+0-9() .-]{6,32}$/D', $phone))) {
			throw new \InvalidArgumentException('Invalid demand details.');
		}
		$city = $this->em->find(City::class, (int) ($data['cityId'] ?? 0));
		$categoryId = (int) ($data['categoryId'] ?? 0);
		$category = $categoryId > 0 ? $this->em->find(CategoryService::class, $categoryId) : null;
		if (!$city instanceof City || !$city->getUse() || ($categoryId !== 0 && (!$category instanceof CategoryService || !$category->isSelectable()))) {
			throw new \InvalidArgumentException('Invalid demand city or category.');
		}
		$demand = new Demand(bin2hex(random_bytes(16)), $submissionKey, $description, $city, $category, $term, $name, $email, $phone ?: null, $customer);
		$raw = $customer === null ? $demand->issueAccess() : null;
		$this->em->wrapInTransaction(function () use ($demand): void { $this->em->persist($demand); });
		if ($raw !== null) {
			$this->em->afterCommit(function () use ($demand, $raw): void { $this->sendAccess($demand, $raw); });
		}
		return $demand;
	}

	public function findByReference(string $reference): ?Demand
	{
		if (!preg_match('/^[a-f0-9]{32}$/D', $reference)) {
			return null;
		}
		return $this->demands->findByReference($reference);
	}

	public function findByToken(string $raw): ?Demand
	{
		if (!preg_match('/^[a-f0-9]{64}$/D', $raw)) {
			return null;
		}
		$hash = hash('sha256', $raw);
		$demand = $this->demands->findByAccessHash($hash);
		return $demand instanceof Demand && $demand->acceptsAccessHash($hash) ? $demand : null;
	}

	/** @return Demand[] */
	public function forCustomer(User $customer, int $page = 1): array
	{
		return $this->demands->forCustomer($customer, $page);
	}

	public function changeStatus(Demand $demand, ?User $customer, ?string $accessHash, bool $close): void
	{
		if (!$this->canAccess($demand, $customer, $accessHash)) {
			throw new \InvalidArgumentException('Demand access denied.');
		}
		$this->em->wrapInTransaction(function () use ($demand, $customer, $accessHash, $close): void {
			$this->demands->refreshForUpdate($demand);
			if (!$this->canAccess($demand, $customer, $accessHash)) {
				throw new \InvalidArgumentException('Demand access denied.');
			}
			$close ? $demand->close() : $demand->verify();
		});
	}

	public function canAccess(Demand $demand, ?User $customer, ?string $accessHash): bool
	{
		return ($customer !== null && $this->accounts->findActiveCustomer($customer->getId()) !== null && $demand->isOwnedBy($customer))
			|| ($accessHash !== null && $demand->acceptsAccessHash($accessHash));
	}

	public function requestAccess(string $reference, string $email): void
	{
		$demand = $this->findByReference($reference);
		if ($demand === null || $demand->getCustomer() !== null || $demand->getContactEmail() !== Emails::normalize($email)) {
			return;
		}
		$raw = $this->em->wrapInTransaction(function () use ($demand, $email): ?string {
			$this->demands->refreshForUpdate($demand);
			if ($demand->getCustomer() !== null || $demand->getContactEmail() !== Emails::normalize($email)) { return null; }
			return $demand->issueAccess();
		});
		if ($raw !== null) {
			$this->em->afterCommit(function () use ($demand, $raw): void { $this->sendAccess($demand, $raw); });
		}
	}

	/** @return array<int, string> */
	public function cityOptions(): array
	{
		$result = [];
		foreach ($this->em->getRepository(City::class)->findBy(['use' => true], ['fullname' => 'ASC']) as $city) {
			$result[$city->getId()] = $city->getFullname() . ' (' . $city->getZip() . ')';
		}
		return $result;
	}

	/** @return array<int, string> */
	public function categoryOptions(): array
	{
		$result = [];
		foreach ($this->em->getRepository(CategoryService::class)->findBy(['dateDeleted' => null], ['name' => 'ASC']) as $category) {
			if (!$category->isSelectable()) { continue; }
			$result[$category->getId()] = ($category->getParent() ? $category->getParent()->getName() . ' / ' : '') . $category->getName();
		}
		return $result;
	}

	/**
	 * Public service catalogue: main categories with the services customers can choose.
	 *
	 * @return list<array{id: int, name: string, description: ?string, standalone: bool, categories: array<int, string>}>
	 */
	public function categoryGroups(): array
	{
		return CategoryCatalogue::groups($this->em->getRepository(CategoryService::class)->findBy(['dateDeleted' => null], ['name' => 'ASC']));
	}

	private function sendAccess(Demand $demand, string $raw): void
	{
		$link = $this->links->link('Front:Demand:access', ['token' => $raw]);
		try {
			$this->mail->sendCustomerLink($demand->getContactEmail(), 'Váš dopyt na Bripeone',
				'Otvorte dopyt a potvrďte svoj e-mail. Odkaz na správu dopytu platí 30 dní. Číslo dopytu: ' . $demand->getReference(), $link);
		} catch (\Throwable $e) {
			// Preserve the saved demand and allow link recovery. Never log mail contents or raw tokens.
			\Tracy\Debugger::log('Demand access email delivery failed: ' . $demand->getReference(), 'error');
		}
	}
}
