<?php declare(strict_types=1);

// Continues the isolated SQLite suite. No configured database is ever opened.
$security->login($admin->toIdentity());
$audit = new \App\Domain\Admin\AdminAuditFacade($em, $security);
$adminDemands = new \App\Domain\Admin\AdminDemandFacade($em, $audit, $matching, $invitations);
$rollbackDemand = $makeDemand();
$rollbackDemandId = $rollbackDemand->getId();
$auditCount = $em->getRepository(\App\Domain\Admin\AdminAudit::class)->count([]);
$notifications = [];

// Inner application services do not open/commit a separate transaction.
$em->wrapInTransaction(function () use ($em, &$notifications): void {
    $depth = $em->getConnection()->getTransactionNestingLevel();
    $em->wrapInTransaction(function () use ($em, $depth, &$notifications): void {
        \Tester\Assert::same($depth, $em->getConnection()->getTransactionNestingLevel());
        $em->afterCommit(function () use (&$notifications): void { $notifications[] = 'committed'; });
    });
    \Tester\Assert::same([], $notifications);
});
\Tester\Assert::same(['committed'], $notifications);

// Both an inner mutation and its audit are rolled back when the outer operation fails.
\Tester\Assert::exception(function () use ($em, $adminDemands, $rollbackDemand, $category, &$notifications): void {
    $em->wrapInTransaction(function () use ($em, $adminDemands, $rollbackDemand, $category, &$notifications): void {
        $adminDemands->requeue($rollbackDemand, $category->getId());
        $rollbackDemand->close();
        $em->flush(); // Even an early flush must be rolled back and its UnitOfWork discarded.
        $em->afterCommit(function () use (&$notifications): void { $notifications[] = 'rolled back'; });
        throw new RuntimeException('Simulated failure after audit.');
    });
}, RuntimeException::class);
\Tester\Assert::true($em->isOpen());
\Tester\Assert::false($em->contains($rollbackDemand));
$restored = $em->find(\App\Domain\Demand\Demand::class, $rollbackDemandId);
\Tester\Assert::same(\App\Domain\Demand\Demand::READY, $restored->getStatus());
\Tester\Assert::same($auditCount, $em->getRepository(\App\Domain\Admin\AdminAudit::class)->count([]));
\Tester\Assert::same(['committed'], $notifications);

// Catching an inner error cannot accidentally commit its earlier mutation.
\Tester\Assert::exception(function () use ($em, $restored): void {
    $em->wrapInTransaction(function () use ($em, $restored): void {
        try {
            $em->wrapInTransaction(function () use ($restored): void {
                $restored->close();
                throw new RuntimeException('Inner failure.');
            });
        } catch (RuntimeException $e) { /* The transaction must remain rollback-only. */ }
    });
}, LogicException::class);
\Tester\Assert::false($em->contains($restored));
\Tester\Assert::same(\App\Domain\Demand\Demand::READY, $em->find(\App\Domain\Demand\Demand::class, $rollbackDemandId)->getStatus());
\Tester\Assert::same(0, $em->getConnection()->getTransactionNestingLevel());

// An after-commit delivery error cannot roll back an already committed operation.
\Tester\Assert::exception(function () use ($em, $rollbackDemandId): void {
    $em->wrapInTransaction(function () use ($em, $rollbackDemandId): void {
        $em->find(\App\Domain\Demand\Demand::class, $rollbackDemandId)->markNeedsReview('Committed before notification.');
        $em->afterCommit(function (): void { throw new RuntimeException('Notification failed after commit.'); });
    });
}, RuntimeException::class);
\Tester\Assert::same(0, $em->getConnection()->getTransactionNestingLevel());
\Tester\Assert::same(\App\Domain\Demand\Demand::REVIEW, $em->find(\App\Domain\Demand\Demand::class, $rollbackDemandId)->getStatus());

// Invalid provider activation and resend cooldown use an isolated manager too.
$providerRepository = new \App\Infrastructure\Persistence\Doctrine\DoctrineProviderRepository($em);
$providerFacade = new \App\Domain\Provider\ProviderFacade($em, new \App\Model\Mail\MailSender($factory), $links, $providerRepository);
$testCity = $em->find(\App\Domain\City\City::class, $city->getId());
$testRole = $em->getRepository(\App\Domain\LoginRole\LoginRole::class)->findOneBy(['name' => 'provider']);
$fresh = $em->find(\App\Domain\StateProvider\StateProvider::class, 1);
$provider = new \App\Domain\Provider\Provider('Concurrency test', 'First', 'Last', '', 'transaction-provider@example.test',
    '+421900000001', '', '', 'unused', 'Street 1', $testCity, '90001', $fresh, $testRole);
$token = \App\Domain\Provider\ActivationToken::generate();
$provider->setActivationToken($token->hash, $token->expiresAt, new DateTimeImmutable('-10 minutes'));
$em->persist($provider); $em->flush();
$before = count($sender->messages);
$providerFacade->resendActivation($provider->getEmail());
$rotated = $provider->getActivationTokenHash();
\Tester\Assert::notSame($token->hash, $rotated);
$providerFacade->resendActivation($provider->getEmail());
\Tester\Assert::same($rotated, $provider->getActivationTokenHash());
\Tester\Assert::same($before + 1, count($sender->messages));
\Tester\Assert::exception(fn() => $providerFacade->activate($token->raw), \App\Domain\Provider\Exception\InvalidActivationTokenException::class);
$raw = $sender->token();
$providerFacade->activate($raw);
\Tester\Assert::true($provider->isActivated());
$providerFacade->resendActivation($provider->getEmail());
\Tester\Assert::null($provider->getActivationTokenHash());
\Tester\Assert::exception(fn() => $providerFacade->activate($raw), \App\Domain\Provider\Exception\InvalidActivationTokenException::class);
\Tester\Assert::true($em->isOpen());
$security->logout(true);
echo "Nested transactions, rollback, audit and provider token checks passed.\n";
