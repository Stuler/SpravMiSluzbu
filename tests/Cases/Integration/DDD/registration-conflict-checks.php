<?php declare(strict_types=1);

// Simulate the stale pre-insert lookup of a concurrent registration, without a server database.
$raceEm = new \App\Model\Database\EntityManagerDecorator(\Doctrine\ORM\EntityManager::create($em->getConnection(), $config));
$raceRepository = new class(new \App\Infrastructure\Persistence\Doctrine\DoctrineProviderRepository($raceEm)) implements \App\Domain\Provider\ProviderRepository {
    public function __construct(private \App\Domain\Provider\ProviderRepository $actual) {}
    public function findByEmail(string $email): ?\App\Domain\Provider\Provider { return null; }
    public function emailExists(string $email): bool { return $this->actual->emailExists($email); }
    public function find(int $id): ?\App\Domain\Provider\Provider { return $this->actual->find($id); }
    public function findByActivationHash(string $hash): ?\App\Domain\Provider\Provider { return $this->actual->findByActivationHash($hash); }
    public function findByActivationHashForUpdate(string $hash): ?\App\Domain\Provider\Provider { return $this->actual->findByActivationHashForUpdate($hash); }
    public function refreshForUpdate(\App\Domain\Provider\Provider $provider): void { $this->actual->refreshForUpdate($provider); }
    public function eligibleForDemand(\App\Domain\Demand\Demand $demand, array $categoryIds): array { return $this->actual->eligibleForDemand($demand, $categoryIds); }
};
$facade = new \App\Domain\Provider\ProviderFacade($raceEm, new \App\Model\Mail\MailSender($factory), $links, $raceRepository);
$activeCategory = $raceEm->getRepository(\App\Domain\CategoryService\CategoryService::class)->findOneBy(['name' => 'Cleaning']);
$registrationData = [
    'firstName' => 'First', 'lastName' => 'Last', 'email' => 'transaction-provider@example.test',
    'password' => 'test-password', 'confirmPassword' => 'test-password', 'phone' => '+421900000001',
    'companyName' => 'Duplicate registration', 'street' => 'Street', 'streetNumber' => '1',
    'cityId' => (string) $city->getId(), 'zip' => '90001',
    'coveredRegions' => [['value' => $region->getId()]], 'serviceCategories' => [['value' => $activeCategory->getId()]],
];
$nestedDto = \App\Domain\Provider\DTO\ProviderRegistrationDataFactory::fromArray(
    array_replace($registrationData, ['email' => 'nested-provider@example.test']));
$beforeNestedMail = count($sender->messages);
$nestedResult = $em->wrapInTransaction(function () use ($providerFacade, $nestedDto, $sender, $beforeNestedMail) {
    $result = $providerFacade->register($nestedDto);
    \Tester\Assert::true($result->providerId > 0);
    \Tester\Assert::same($beforeNestedMail, count($sender->messages));
    return $result;
});
\Tester\Assert::same($beforeNestedMail + 1, count($sender->messages));
\Tester\Assert::notNull($em->find(\App\Domain\Provider\Provider::class, $nestedResult->providerId));
$dto = \App\Domain\Provider\DTO\ProviderRegistrationDataFactory::fromArray($registrationData);
$before = count($sender->messages);
$beforeProviders = $em->getRepository(\App\Domain\Provider\Provider::class)->count([]);
\Tester\Assert::exception(fn() => $facade->register($dto), \App\Domain\Provider\Exception\DuplicateEmailException::class);
\Tester\Assert::false($raceEm->isOpen());
\Tester\Assert::same(0, $em->getConnection()->getTransactionNestingLevel());
\Tester\Assert::same($beforeProviders, $em->getRepository(\App\Domain\Provider\Provider::class)->count([]));
\Tester\Assert::same($before, count($sender->messages));
echo "Concurrent email-conflict handling passed.\n";
