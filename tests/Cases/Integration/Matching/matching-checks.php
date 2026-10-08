<?php declare(strict_types=1);

use App\Domain\Demand\Demand;
use App\Domain\Matching\MatchingFacade;
use App\Domain\Matching\ProviderInvitation;
use App\Domain\Provider\Provider;
use App\Domain\ProviderRegion\ProviderRegion;
use App\Domain\ProviderServiceCategory\ProviderServiceCategory;
use App\Domain\StateProvider\StateProvider;
use Tester\Assert;

// Continue using the isolated SQLite database and in-memory mail transport.
foreach ($em->getRepository(Demand::class)->findAll() as $existingDemand) { $existingDemand->close(); }
$category->setDateDeleted(null);
$em->flush();
foreach (['fresh', 'active', 'blocked'] as $name) {
    $state = new StateProvider(); $state->setName($name); $state->setDateCreated(new DateTime()); $em->persist($state);
}
$providerRole = new \App\Domain\LoginRole\LoginRole(); $providerRole->setName('provider'); $em->persist($providerRole); $em->flush();
$makeProvider = function (string $name, int $stateId = 2, bool $accepts = true, bool $covered = true) use ($em, $city, $providerRole, $region, $category): Provider {
    $provider = new Provider(companyName: $name, contactName: 'Provider', contactSurname: 'Test', contactTitle: '', email: strtolower($name) . '@example.test',
        phoneNumber: '+421900000001', ico: '', dic: '', password: 'unused', streetNo: 'Test', city: $city, zipCode: '90001',
        stateProvider: $em->find(StateProvider::class, $stateId), loginRole: $providerRole);
    $provider->setAcceptsDemands($accepts);
    $provider->setServiceDescription('Upratovanie domácností.');
    $em->persist($provider);
    if ($covered) { $relation = new ProviderRegion($provider, $region); $relation->setDateCreated(new DateTime()); $em->persist($relation); }
    $relation = new ProviderServiceCategory($provider, $category); $relation->setDateCreated(new DateTime()); $em->persist($relation);
    $em->flush();
    return $provider;
};
$eligible = [$makeProvider('Alpha'), $makeProvider('Beta'), $makeProvider('Gamma'), $makeProvider('Delta')];
$blockedProvider = $makeProvider('Blocked', 3);
$pausedProvider = $makeProvider('Paused', 2, false);
$outsideProvider = $makeProvider('Outside', 2, true, false);
$freshProvider = $makeProvider('Fresh', 1);
$deletedProvider = $makeProvider('Deleted'); $deletedProvider->setDateDeleted(new DateTime()); $em->flush();
$fakeAi = new class($category->getId()) implements \App\Domain\Matching\StructuredAiClient {
    public bool $invalidProvider = false;
    public bool $unavailable = false;
    public array $calls = [];
    public function __construct(private int $categoryId) {}
    public function complete(string $prompt, array $input, array $schema): array {
        if ($this->unavailable) { throw new \App\Domain\Matching\AiUnavailableException('Not configured.'); }
        $this->calls[] = $input;
        $data = isset($input['catalogue'])
            ? ['category_ids' => [$this->categoryId], 'needs_review' => false, 'summary' => 'Upratovanie dvojizbového bytu.', 'review_reason' => '']
            : ['selection' => $this->invalidProvider ? [['provider_id' => 999999, 'reason' => 'Invalid']]
                : array_map(fn($p) => ['provider_id' => $p['id'], 'reason' => 'Poskytuje požadovanú službu.'], array_slice($input['candidates'], 0, 3))];
        return ['data' => $data, 'model' => 'offline-test', 'inputTokens' => 10, 'outputTokens' => 10, 'durationMs' => 1];
    }
};
$matching = new MatchingFacade($em, $fakeAi, APP_DIR . '/../resources/prompts');
$makeDemand = function () use ($demands, $data, $other): Demand {
    return $demands->create(array_replace($data, ['categoryId' => null]), $other, bin2hex(random_bytes(32)));
};
$matchedDemand = $makeDemand();
Assert::same(array_map(fn($p) => $p->getId(), $eligible), array_map(fn($p) => $p->getId(), $matching->eligibleProviders($matchedDemand, [$category->getId()])));
$beforeMail = count($sender->messages);
$preview = $matching->preview($matchedDemand);
Assert::count(3, $preview['selection']);
Assert::count(0, $matching->invitations($matchedDemand));
Assert::same(Demand::READY, $matchedDemand->getStatus());
Assert::same($beforeMail, count($sender->messages));
Assert::true($matching->processOne());
Assert::same(Demand::INVITED, $matchedDemand->getStatus());
Assert::count(3, $matching->invitations($matchedDemand));
Assert::false($matching->processOne());
Assert::same($beforeMail, count($sender->messages)); // Matching never sends mail itself.
$matching->requeue($matchedDemand, $category->getId());
Assert::true($matching->processOne());
Assert::count(3, $matching->invitations($matchedDemand)); // No duplicate or extra invitations after reprocessing.
for ($i = 0; $i < 3; $i++) { Assert::true($invitations->deliverOne()); }
Assert::false($invitations->deliverOne());
Assert::same($beforeMail + 3, count($sender->messages));
$invitation = $matching->invitations($matchedDemand)[0];
Assert::same('sent', $invitation->getDelivery());
Assert::null($invitations->findForProvider($invitation->getId(), $eligible[3]));
Assert::exception(fn() => $invitations->respond($invitation->getId(), $eligible[3], true), InvalidArgumentException::class);
$invitations->respond($invitation->getId(), $eligible[0], true);
$invitations->respond($invitation->getId(), $eligible[0], false);
Assert::same('accepted', $invitation->getResponse()); // First response is final.

// Invalid model IDs can never become invitations, and transient failures have a bounded retry window.
$invalidDemand = $makeDemand();
$fakeAi->invalidProvider = true;
Assert::true($matching->processOne());
Assert::count(0, $matching->invitations($invalidDemand));
Assert::same(Demand::READY, $invalidDemand->getStatus());
Assert::false($matching->processOne()); // Backoff prevents a tight retry loop.
$fakeAi->invalidProvider = false;
$matching->requeue($invalidDemand, $category->getId());
$fakeAi->unavailable = true;
Assert::true($matching->processOne());
Assert::same(Demand::REVIEW, $invalidDemand->getStatus());
$fakeAi->unavailable = false;

// Admin fallback still obeys eligibility and the lifetime invitation cap.
$matching->approveManually($invalidDemand, [$eligible[0]->getId(), $blockedProvider->getId()]);
Assert::count(1, $matching->invitations($invalidDemand));
$invalidDemand->close(); $em->flush();
Assert::true($invitations->deliverOne());
Assert::same('cancelled', $matching->invitations($invalidDemand)[0]->getDelivery());
Assert::same($beforeMail + 3, count($sender->messages));
Assert::exception(fn() => $invitations->respond($matching->invitations($invalidDemand)[0]->getId(), $eligible[0], true), InvalidArgumentException::class);

// Closed/unverified demands never enter the worker. No provider matches is a visible terminal processing result.
foreach ($eligible as $provider) { $provider->setAcceptsDemands(false); } $em->flush();
$unmatched = $makeDemand();
Assert::true($matching->processOne());
Assert::same(Demand::NO_PROVIDERS, $unmatched->getStatus());
$pending = $demands->create($data, null, bin2hex(random_bytes(32)));
Assert::same(Demand::PENDING, $pending->getStatus());
Assert::false($matching->processOne());
foreach ($eligible as $provider) { $provider->setAcceptsDemands(true); } $em->flush();

// Provider pages show contact only after acceptance and enforce identity + invitation ownership.
$security->login($eligible[0]->toIdentity());
[$response, $html] = $run('Front:ProviderDemand', ['action' => 'default']);
Assert::contains('Dopyty pre vašu firmu', $html);
[$response, $html] = $run('Front:ProviderDemand', ['action' => 'detail', 'id' => $invitation->getId()]);
Assert::contains($matchedDemand->getContactEmail(), html_entity_decode($html));
$security->login($eligible[1]->toIdentity());
$pendingInvitation = $matching->invitations($matchedDemand)[1];
[$response, $html, $providerPresenter] = $run('Front:ProviderDemand', ['action' => 'detail', 'id' => $pendingInvitation->getId()]);
Assert::notContains($matchedDemand->getContactEmail(), html_entity_decode($html));
Assert::exception(fn() => $run('Front:ProviderDemand', ['action' => 'detail', 'id' => $invitation->getId()]), \Nette\Application\BadRequestException::class);
$post = ['accept' => '1', 'submit' => 'Potvrdiť', \Nette\Forms\Form::ProtectorId => $providerPresenter['responseForm'][\Nette\Forms\Form::ProtectorId]->getControl()->getAttribute('value')];
[$response] = $run('Front:ProviderDemand', ['action' => 'detail', 'id' => $pendingInvitation->getId(), 'do' => 'responseForm-submit'], 'POST', $post);
Assert::same('accepted', $pendingInvitation->getResponse());
$security->login($blockedProvider->toIdentity());
[$response] = $run('Front:ProviderDemand', ['action' => 'default']);
Assert::type(\Nette\Application\Responses\RedirectResponse::class, $response);
$security->logout(true);

// An expired lease is replaced; the old holder cannot commit its result.
$leaseDemand = $makeDemand();
$firstLease = $leaseDemand->claimProcessing();
(new ReflectionProperty(Demand::class, 'leaseUntil'))->setValue($leaseDemand, new DateTimeImmutable('-1 second'));
Assert::false($leaseDemand->ownsLease($firstLease));
$secondLease = $leaseDemand->claimProcessing();
Assert::false($leaseDemand->ownsLease($firstLease));
Assert::true($leaseDemand->ownsLease($secondLease));
$leaseDemand->close(); $em->flush();
Assert::false($leaseDemand->ownsLease($secondLease));

// Ambiguous SMTP results are not retried automatically. Explicit retry uses a fresh locked row.
$mailDemand = $makeDemand();
$matching->requeue($mailDemand, $category->getId());
$matching->approveManually($mailDemand, [$eligible[0]->getId()]);
$sender->fail = true;
Assert::true($invitations->deliverOne());
$sender->fail = false;
$mailInvitation = $matching->invitations($mailDemand)[0];
Assert::same('unknown', $mailInvitation->getDelivery());
Assert::false($invitations->deliverOne());
Assert::exception(fn() => $mailInvitation->retryDelivery(), InvalidArgumentException::class);
(new ReflectionProperty(ProviderInvitation::class, 'deliveryAttemptedAt'))->setValue($mailInvitation, new DateTimeImmutable('-6 minutes'));
$em->flush();
$invitations->retryDelivery($mailInvitation->getId(), $mailDemand);
Assert::true($invitations->deliverOne());
Assert::same('sent', $mailInvitation->getDelivery());

// Admin authorization, real templates, and CSRF-protected manual selection.
$security->login($other->toIdentity());
[$response] = $run('Admin:Demand', ['action' => 'default']);
Assert::type(\Nette\Application\Responses\RedirectResponse::class, $response);
$admin = new \App\Domain\User\User(name: 'Test', surname: 'Admin', email: 'admin@example.test', password: 'unused', loginRole: $adminRole,
    stateUser: $em->find(\App\Domain\StateUser\StateUser::class, 2), streetNo: null, city: null, zipCode: null, hash: '');
$em->persist($admin); $em->flush();
$security->login($admin->toIdentity());
[$response, $html] = $run('Admin:Demand', ['action' => 'default']);
Assert::contains('Dopyty', $html);
[$response, $html, $adminPresenter] = $run('Admin:Demand', ['action' => 'detail', 'reference' => $mailDemand->getReference()]);
Assert::contains('História AI spracovania', $html);
$adminDemand = $makeDemand();
$matching->requeue($adminDemand, $category->getId());
[$response, $html, $adminPresenter] = $run('Admin:Demand', ['action' => 'detail', 'reference' => $adminDemand->getReference()]);
$post = ['providers' => [(string) $eligible[0]->getId()], 'submit' => 'Potvrdiť'];
$run('Admin:Demand', ['action' => 'detail', 'reference' => $adminDemand->getReference(), 'do' => 'approveForm-submit'], 'POST', $post);
Assert::count(0, $matching->invitations($adminDemand));
$post[\Nette\Forms\Form::ProtectorId] = $adminPresenter['approveForm'][\Nette\Forms\Form::ProtectorId]->getControl()->getAttribute('value');
$run('Admin:Demand', ['action' => 'detail', 'reference' => $adminDemand->getReference(), 'do' => 'approveForm-submit'], 'POST', $post);
Assert::count(1, $matching->invitations($adminDemand));
$security->logout(true);

// Command defaults never send provider emails; evaluation without --live never calls AI.
$beforeCalls = count($fakeAi->calls);
$evaluation = new \Symfony\Component\Console\Tester\CommandTester(new \App\Console\DemandEvaluateCommand($fakeAi, APP_DIR . '/..'));
Assert::same(0, $evaluation->execute([]));
Assert::same($beforeCalls, count($fakeAi->calls));
$beforeMail = count($sender->messages);
$worker = new \Symfony\Component\Console\Tester\CommandTester(new \App\Console\DemandWorkerCommand($matching, $invitations));
Assert::same(0, $worker->execute(['--limit' => '1']));
Assert::same($beforeMail, count($sender->messages));
Assert::same(0, $worker->execute(['--send' => true, '--limit' => '1']));
Assert::same($beforeMail + 1, count($sender->messages));
