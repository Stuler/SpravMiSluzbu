<?php declare(strict_types=1);
namespace App\Domain\Admin;

use App\Domain\CategoryService\CategoryService;
use App\Domain\City\City;
use App\Domain\Customer\CustomerAccountFacade;
use App\Domain\Customer\CustomerToken;
use App\Domain\LoginRole\LoginRole;
use App\Domain\Provider\Provider;
use App\Domain\Provider\ProviderFacade;
use App\Domain\ProviderRegion\ProviderRegion;
use App\Domain\ProviderServiceCategory\ProviderServiceCategory;
use App\Domain\Region\Region;
use App\Domain\User\User;
use App\Model\Database\EntityManagerDecorator;
use App\Model\Utils\PhoneNumbers;
use Doctrine\DBAL\LockMode;

final class AdminAccountFacade
{
    public function __construct(private EntityManagerDecorator $em, private AdminAuditFacade $audit,
        private ProviderFacade $providers, private CustomerAccountFacade $customers) {}
    public function provider(int $id): ?Provider
    {
        $this->audit->requireAdmin();
        $provider = $this->em->find(Provider::class, $id);
        return $provider instanceof Provider && $provider->getDateDeleted() === null ? $provider : null;
    }
    public function customer(int $id): ?User
    {
        $this->audit->requireAdmin();
        $user = $this->em->find(User::class, $id);
        return $user instanceof User && $user->getDateDeleted() === null && $user->getLoginRole()->getName() === LoginRole::ROLE_MEMBER ? $user : null;
    }
    public function setBlocked(Provider|User $account, bool $blocked): void
    {
        $this->audit->requireAdmin(); $this->assertManagedAccount($account);
        $this->em->wrapInTransaction(function () use ($account, $blocked): void {
            $this->em->refresh($account, LockMode::PESSIMISTIC_WRITE);
            $this->assertManagedAccount($account);
            if ($account->isBlocked() === $blocked) { return; }
            $account->setAdministrativelyBlocked($blocked);
            $this->audit->record($account instanceof Provider ? 'provider' : 'customer', $account->getId(), $blocked ? 'blocked' : 'unblocked');
        });
    }
    public function updateCustomer(User $customer, string $name, string $surname): void
    {
        $this->audit->requireAdmin(); $this->assertManagedAccount($customer);
        $name = $this->text($name, 255, true); $surname = $this->text($surname, 255);
        $this->em->wrapInTransaction(function () use ($customer, $name, $surname): void {
            $this->em->refresh($customer, LockMode::PESSIMISTIC_WRITE); $this->assertManagedAccount($customer);
            $customer->updateName($name, $surname); $customer->setDateModified(new \DateTime());
            $this->audit->record('customer', $customer->getId(), 'profile_updated');
        });
    }
    public function updateProvider(Provider $provider, array $data): void
    {
        $this->audit->requireAdmin(); $this->assertManagedAccount($provider);
        $limits = ['companyName' => 255, 'contactName' => 64, 'surname' => 64, 'title' => 32, 'phone' => 100,
            'ico' => 100, 'dic' => 100, 'streetNo' => 100, 'zipCode' => 100, 'description' => 2000];
        foreach ($limits as $key => $limit) {
            $data[$key] = $this->text((string) ($data[$key] ?? ''), $limit, in_array($key, ['companyName', 'contactName', 'surname', 'phone', 'streetNo', 'zipCode'], true));
        }
        if (!PhoneNumbers::isValid($data['phone'])) { throw new \InvalidArgumentException('Invalid provider phone.'); }
        $city = $this->em->find(City::class, (int) ($data['city'] ?? 0));
        if (!$city instanceof City || !$city->getUse()) { throw new \InvalidArgumentException('Invalid provider city.'); }
        $regions = $this->resolve(Region::class, $data['regions'] ?? []);
        $categories = $this->resolve(CategoryService::class, $data['categories'] ?? []);
        $this->em->wrapInTransaction(function () use ($provider, $data, $city, $regions, $categories): void {
            $this->em->refresh($provider, LockMode::PESSIMISTIC_WRITE); $this->assertManagedAccount($provider);
            $provider->updateProfile($data['companyName'], $data['contactName'], $data['surname'], $data['title'],
                PhoneNumbers::normalize($data['phone']), $data['ico'], $data['dic'], $data['streetNo'], $city, $data['zipCode']);
            $provider->setServiceDescription($data['description'] ?: null);
            $before = $provider->acceptsDemands(); $provider->setAcceptsDemands((bool) ($data['accepts'] ?? false));
            $provider->setDateModified(new \DateTime());
            $changes = ['accepts_before' => $before, 'accepts_after' => $provider->acceptsDemands()];
            $changes['regions'] = $this->replaceRelations($provider, ProviderRegion::class, $regions);
            $changes['categories'] = $this->replaceRelations($provider, ProviderServiceCategory::class, $categories);
            $this->audit->record('provider', $provider->getId(), 'profile_updated');
            $this->audit->record('provider', $provider->getId(), 'coverage_updated', $changes);
        });
    }
    public function sendLink(Provider|User $account, string $purpose): void
    {
        $this->audit->requireAdmin(); $this->assertManagedAccount($account);
        if ($account->isBlocked() || !in_array($purpose, [CustomerToken::ACTIVATE, CustomerToken::RESET], true)) { throw new \InvalidArgumentException('Link cannot be issued.'); }
        if ($purpose === CustomerToken::RESET && ($account instanceof Provider || !$account->isActivated())) { throw new \InvalidArgumentException('Recovery requires an active customer.'); }
        if ($purpose === CustomerToken::ACTIVATE && $account->isActivated()) { throw new \InvalidArgumentException('Account is already activated.'); }
        $this->audit->record($account instanceof Provider ? 'provider' : 'customer', $account->getId(),
            $purpose === CustomerToken::ACTIVATE ? 'activation_requested' : 'reset_requested');
        $this->em->flush();
        if ($account instanceof Provider) { $this->providers->resendActivation($account->getEmail()); }
        else { $this->customers->requestToken($account->getEmail(), $purpose); }
    }
    private function assertManagedAccount(Provider|User $account): void
    {
        if ($account->getDateDeleted() !== null || ($account instanceof User && $account->getLoginRole()->getName() !== LoginRole::ROLE_MEMBER)) {
            throw new \InvalidArgumentException('Account is outside the customer/provider administration scope.');
        }
    }
    private function text(string $value, int $limit, bool $required = false): string
    {
        $value = trim($value);
        if (($required && $value === '') || mb_strlen($value) > $limit) { throw new \InvalidArgumentException('Invalid profile field.'); }
        return $value;
    }
    private function resolve(string $class, array $ids): array
    {
        if ($ids === []) { throw new \InvalidArgumentException('Select at least one service and region.'); }
        $result = [];
        foreach (array_unique(array_map('intval', $ids)) as $id) {
            $entity = $this->em->find($class, $id);
            if ($entity === null || ($entity instanceof CategoryService && !$entity->isSelectable())
                || ($entity instanceof Region && !$entity->getUse())) { throw new \InvalidArgumentException('Unknown or retired coverage.'); }
            $result[] = $entity;
        }
        return $result;
    }
    private function replaceRelations(Provider $provider, string $class, array $entities): array
    {
        $after = array_map(fn($entity): int => $entity->getId(), $entities); $before = []; $existing = [];
        foreach ($this->em->getRepository($class)->findBy(['provider' => $provider]) as $relation) {
            $id = ($relation instanceof ProviderRegion ? $relation->getRegion() : $relation->getCategoryService())->getId();
            if ($relation->getDateDeleted() === null) { $before[] = $id; }
            $keep = in_array($id, $after, true) && !isset($existing[$id]);
            if ($keep) { $relation->setDateDeleted(null); $existing[$id] = true; }
            elseif ($relation->getDateDeleted() === null) { $relation->setDateDeleted(new \DateTime()); }
        }
        foreach ($entities as $entity) {
            if (!isset($existing[$entity->getId()])) { $this->em->persist(new $class($provider, $entity)); }
        }
        sort($before); sort($after);
        return ['before' => $before, 'after' => $after];
    }
}
