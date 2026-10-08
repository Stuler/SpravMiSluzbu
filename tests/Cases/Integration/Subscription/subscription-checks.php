<?php declare(strict_types=1);

// Included by the isolated customer suite, using its in-memory SQLite fixtures.
$plan = new \App\Domain\SubscriptionPlan\SubscriptionPlan('Monthly test', 'month', 1, 1500, 'price_local');
$state = new \App\Domain\StateSubscription\StateSubscription('pending');
$subscription = new \App\Domain\Subscription\Subscription($eligible[0], 'sub_local', $plan, $state,
    new DateTimeImmutable('2026-10-08 12:00:00'));
$em->persist($plan); $em->persist($state); $em->persist($subscription); $em->flush();
$id = $subscription->getId();
$reader = \Doctrine\ORM\EntityManager::create($em->getConnection(), $config);
$restored = $reader->find(\App\Domain\Subscription\Subscription::class, $id);
\Tester\Assert::notSame($subscription, $restored);
\Tester\Assert::same('pending', $restored->getStatus()->getName());
\Tester\Assert::same('sub_local', $restored->getStripeSubscriptionId());
\Tester\Assert::same('2026-10-08 12:00:00', $restored->getStartedAt()->format('Y-m-d H:i:s'));
\Tester\Assert::same($eligible[0]->getId(), $restored->getProvider()->getId());
