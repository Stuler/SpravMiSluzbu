<?php declare(strict_types=1);

// Continues the isolated SQLite fixtures and real presenter harness; no configured database or SMTP.
use App\Domain\Admin\AdminAccountFacade;
use App\Domain\Admin\AdminAudit;
use App\Domain\Admin\AdminAuditFacade;
use App\Domain\Admin\AdminReadFacade;
use App\Domain\CategoryService\CategoryService;
use App\Domain\CategoryService\CategoryServiceFacade;
use App\Domain\Customer\CustomerToken;
use App\Domain\Demand\Demand;
use App\Domain\Provider\ProviderFacade;
use Nette\Application\Responses\RedirectResponse;
use Nette\Forms\Form;
use Nette\Utils\ArrayHash;
use Tester\Assert;

$security->login($admin->toIdentity());
$audit = new AdminAuditFacade($em, $security);
$read = new AdminReadFacade($em, $audit);
$providerFacade = new ProviderFacade($em, new \App\Model\Mail\MailSender($factory), $links, new \App\Infrastructure\Persistence\Doctrine\DoctrineProviderRepository($em));
$adminAccounts = new AdminAccountFacade($em, $audit, $providerFacade, $accounts);
$categoryFacade = new CategoryServiceFacade($em, $audit);

// Every new screen renders real Latte and forms under an actual active admin account.
foreach ([['Admin:Home', ['action' => 'default']], ['Admin:Provider', ['action' => 'default']],
    ['Admin:Provider', ['action' => 'detail', 'id' => $eligible[0]->getId()]], ['Admin:Customer', ['action' => 'default']],
    ['Admin:Customer', ['action' => 'detail', 'id' => $other->getId()]], ['Admin:CategoryService', ['action' => 'default']],
    ['Admin:CategoryService', ['action' => 'edit']], ['Admin:CategoryService', ['action' => 'edit', 'id' => $category->getId()]]]
    as [$name, $params]) {
    [$response, $html] = $run($name, $params);
    Assert::type(\Nette\Application\Responses\TextResponse::class, $response);
    Assert::contains('<h1>', $html);
    Assert::notContains('n:href', $html);
}

// Account type prevents provider/customer ID collisions; a fabricated or inactive admin cannot access data.
foreach ([$other->toIdentity(), $eligible[0]->toIdentity()] as $identity) {
    $security->login($identity);
    foreach (['Admin:Home', 'Admin:Provider', 'Admin:Customer', 'Admin:CategoryService'] as $name) {
        [$response] = $run($name, ['action' => 'default']); Assert::type(RedirectResponse::class, $response);
    }
    Assert::exception(fn() => $read->providers([], 1), \Nette\Application\ForbiddenRequestException::class);
    Assert::exception(fn() => $adminAccounts->setBlocked($other, true), \Nette\Application\ForbiddenRequestException::class);
}
$security->logout(true);
[$response] = $run('Admin:Provider', ['action' => 'default']); Assert::type(RedirectResponse::class, $response);
$security->login(new \App\Model\Security\Identity(999999, ['admin'], ['accountType' => 'user']));
Assert::exception(fn() => $run('Admin:Customer', ['action' => 'default']), \Nette\Application\ForbiddenRequestException::class);
$security->login($admin->toIdentity());

// Missing or forged CSRF cannot mutate account state, names or categories, and emits no audit record.
[$response, $html, $p] = $run('Admin:Customer', ['action' => 'detail', 'id' => $other->getId()]);
$beforeAudit = $em->getRepository(AdminAudit::class)->count([]);
$post = ['confirm' => '1', 'submit' => 'Zablokovať účet'];
$run('Admin:Customer', ['action' => 'detail', 'id' => $other->getId(), 'do' => 'stateForm-submit'], 'POST', $post);
Assert::false($other->isBlocked()); Assert::same($beforeAudit, $em->getRepository(AdminAudit::class)->count([]));
$post[Form::ProtectorId] = 'forged';
$run('Admin:Customer', ['action' => 'detail', 'id' => $other->getId(), 'do' => 'stateForm-submit'], 'POST', $post);
Assert::false($other->isBlocked());
$post[Form::ProtectorId] = $p['stateForm'][Form::ProtectorId]->getControl()->getAttribute('value');
$oldIdentity = $other->toIdentity();
$run('Admin:Customer', ['action' => 'detail', 'id' => $other->getId(), 'do' => 'stateForm-submit'], 'POST', $post);
Assert::true($other->isBlocked()); Assert::false($other->isActivated());
$security->login($oldIdentity);
[$response] = $run('Front:CustomerDashboard', ['action' => 'default']); Assert::type(RedirectResponse::class, $response);
$security->login($admin->toIdentity()); $adminAccounts->setBlocked($other, false);
Assert::true($other->isActivated());
Assert::null($accounts->findActiveCustomer($other->getId(), $oldIdentity->getData()['customerSessionVersion']));
$security->login($oldIdentity);
[$response] = $run('Front:CustomerDashboard', ['action' => 'default']); Assert::type(RedirectResponse::class, $response);
$security->login($admin->toIdentity());

// Blocking/unblocking fresh accounts never verifies email and blocks token consumption while blocked.
$accounts->register('Fresh admin customer', 'admin-fresh@example.test', 'long-password'); $freshToken = $sender->token();
$freshCustomer = $em->getRepository(\App\Domain\User\User::class)->findOneBy(['email' => 'admin-fresh@example.test']);
$adminAccounts->setBlocked($freshCustomer, true);
Assert::false($accounts->isValidToken($freshToken, CustomerToken::ACTIVATE));
$adminAccounts->setBlocked($freshCustomer, false); Assert::false($freshCustomer->isActivated());
$adminAccounts->setBlocked($freshProvider, true); $adminAccounts->setBlocked($freshProvider, false);
Assert::false($freshProvider->isActivated()); Assert::true($freshProvider->isFresh());

// Profile edits do not change historical demand contacts or merge guest requests by email.
$supportDemand = $makeDemand(); $historicContact = $supportDemand->getContactName();
$adminAccounts->updateCustomer($other, 'Updated', 'Customer');
Assert::same($historicContact, $supportDemand->getContactName());
Assert::null($guest->getCustomer());
Assert::null($adminAccounts->customer($admin->getId()));
Assert::exception(fn() => $adminAccounts->updateCustomer($admin, 'Forged', 'Admin'), InvalidArgumentException::class);
$adminAccounts->sendLink($freshCustomer, CustomerToken::ACTIVATE); Assert::true($accounts->isValidToken($sender->token(), CustomerToken::ACTIVATE));
$adminAccounts->sendLink($other, CustomerToken::RESET); Assert::true($accounts->isValidToken($sender->token(), CustomerToken::RESET));
$beforeMail = count($sender->messages); $adminAccounts->sendLink($freshProvider, CustomerToken::ACTIVATE);
Assert::same($beforeMail + 1, count($sender->messages));
$adminAccounts->sendLink($freshProvider, CustomerToken::ACTIVATE); Assert::same($beforeMail + 1, count($sender->messages));

// Blocked or paused providers cannot receive pending invitations. Session revocation survives unblocking.
$d = $makeDemand(); $matching->requeue($d, $category->getId()); $matching->approveManually($d, [$eligible[0]->getId()]);
$oldProviderIdentity = $eligible[0]->toIdentity();
$adminAccounts->setBlocked($eligible[0], true);
Assert::null($invitations->activeProvider($eligible[0]->getId()));
Assert::notContains($eligible[0], $matching->eligibleProviders($d, [$category->getId()]));
$beforeMail = count($sender->messages); Assert::true($invitations->deliverOne());
Assert::same('cancelled', $matching->invitations($d)[0]->getDelivery()); Assert::same($beforeMail, count($sender->messages));
$security->login($oldProviderIdentity);
[$response] = $run('Front:ProviderDemand', ['action' => 'default']); Assert::type(RedirectResponse::class, $response);
$security->login($oldProviderIdentity);
[$response] = $run('Front:ProviderDashboard', ['action' => 'default']); Assert::type(RedirectResponse::class, $response);
$security->login($admin->toIdentity()); $adminAccounts->setBlocked($eligible[0], false);
$security->login($oldProviderIdentity);
[$response] = $run('Front:ProviderDemand', ['action' => 'default']); Assert::type(RedirectResponse::class, $response);
$security->login($oldProviderIdentity);
[$response] = $run('Front:ProviderDashboard', ['action' => 'default']); Assert::type(RedirectResponse::class, $response);
$security->login($admin->toIdentity());

// Root and child editing preserve descriptions, validate cycles, and retire a whole subtree without deleting history.
$categoryFacade->saveCategory(ArrayHash::from(['id' => null, 'name' => 'Admin root', 'parent_id' => null, 'description' => 'Root description']));
$root = $em->getRepository(CategoryService::class)->findOneBy(['name' => 'Admin root']);
$categoryFacade->saveCategory(ArrayHash::from(['id' => null, 'name' => 'Admin child', 'parent_id' => $root->getId(), 'description' => 'Child description']));
$child = $em->getRepository(CategoryService::class)->findOneBy(['name' => 'Admin child']);
$categoryFacade->saveCategory(ArrayHash::from(['id' => $child->getId(), 'name' => 'Edited child', 'parent_id' => $root->getId(), 'description' => 'Edited description']));
Assert::same('Edited description', $child->getDescription());
Assert::exception(fn() => $categoryFacade->saveCategory(ArrayHash::from(['id' => $root->getId(), 'name' => 'Admin root', 'parent_id' => $child->getId()])), InvalidArgumentException::class);
Assert::exception(fn() => $categoryFacade->saveCategory(ArrayHash::from(['id' => $root->getId(), 'name' => 'Admin root', 'parent_id' => $root->getId()])), InvalidArgumentException::class);
Assert::null($root->getParent());

// Category creation and editing are protected POST operations, including root descriptions.
[$response, $html, $p] = $run('Admin:CategoryService', ['action' => 'edit']);
$categoryPost = ['name' => 'UI root', 'parent_id' => '', 'description' => 'UI description', 'submit' => 'Uložiť'];
$run('Admin:CategoryService', ['action' => 'edit', 'do' => 'formCategoryService-submit'], 'POST', $categoryPost);
Assert::null($em->getRepository(CategoryService::class)->findOneBy(['name' => 'UI root']));
$categoryPost[Form::ProtectorId] = $p['formCategoryService'][Form::ProtectorId]->getControl()->getAttribute('value');
$run('Admin:CategoryService', ['action' => 'edit', 'do' => 'formCategoryService-submit'], 'POST', $categoryPost);
$uiRoot = $em->getRepository(CategoryService::class)->findOneBy(['name' => 'UI root']);
Assert::same('UI description', $uiRoot->getDescription()); Assert::null($uiRoot->getParent());
[$response, $html, $p] = $run('Admin:CategoryService', ['action' => 'edit', 'id' => $root->getId()]);
$cyclePost = ['name' => 'Admin root', 'parent_id' => (string) $child->getId(), 'description' => '', 'submit' => 'Uložiť',
    Form::ProtectorId => $p['formCategoryService'][Form::ProtectorId]->getControl()->getAttribute('value')];
[$response, $html, $p] = $run('Admin:CategoryService', ['action' => 'edit', 'id' => $root->getId(), 'do' => 'formCategoryService-submit'], 'POST', $cyclePost);
Assert::true($p['formCategoryService']->hasErrors()); Assert::null($root->getParent()); Assert::true($em->isOpen());

// Coverage changes affect selection immediately while retaining relations and all previous invitations.
$profile = ['companyName' => 'Updated Alpha', 'contactName' => 'Provider', 'surname' => 'Test', 'title' => '', 'phone' => '+421900000001',
    'ico' => '12345678', 'dic' => '', 'streetNo' => 'Test 1', 'zipCode' => '90001', 'city' => $city->getId(), 'description' => 'Updated services',
    'accepts' => true, 'categories' => [$child->getId()], 'regions' => [$region->getId()]];
$historyCount = $em->getRepository(\App\Domain\Matching\ProviderInvitation::class)->count(['provider' => $eligible[0]]);
$adminAccounts->updateProvider($eligible[0], $profile);
Assert::notContains($eligible[0], $matching->eligibleProviders($d, [$category->getId()]));
Assert::contains($eligible[0], $matching->eligibleProviders($d, [$child->getId()]));
Assert::same($historyCount, $em->getRepository(\App\Domain\Matching\ProviderInvitation::class)->count(['provider' => $eligible[0]]));
$alternateRegion = new \App\Domain\Region\Region(); $alternateRegion->setName('Alternate region'); $alternateRegion->setShortcut('AA'); $alternateRegion->setUse(true);
$em->persist($alternateRegion); $em->flush();
$profile['regions'] = [$alternateRegion->getId()]; $adminAccounts->updateProvider($eligible[0], $profile);
Assert::notContains($eligible[0], $matching->eligibleProviders($d, [$child->getId()]));
$profile['regions'] = [$region->getId()]; $adminAccounts->updateProvider($eligible[0], $profile);
Assert::contains($eligible[0], $matching->eligibleProviders($d, [$child->getId()]));
$profile['accepts'] = false; $adminAccounts->updateProvider($eligible[0], $profile);
Assert::notContains($eligible[0], $matching->eligibleProviders($d, [$child->getId()]));
$categoryFacade->deleteCategory($root->getId());
Assert::false($root->isSelectable()); Assert::false($child->isSelectable());
Assert::false(isset($demands->categoryOptions()[$child->getId()]));
Assert::same('Edited child', $providerFacade->getProviderServiceCategories($eligible[0])[0]->getName());
Assert::same([], $matching->eligibleProviders($d, [$child->getId()]));

// Pagination, search, compound account filters, date ranges and overview use database predicates.
Assert::same(1, $read->providers(['q' => '12345678', 'accepts' => '0', 'category' => $child->getId(), 'region' => $region->getId()], 1)['count']);
Assert::same(1, $read->customers(['q' => 'admin-fresh@example.test', 'state' => 'fresh'], 1)['count']);
for ($i = 0; $i < 23; $i++) { $makeDemand(); }
$list = $read->demands(['status' => Demand::READY, 'locality' => 'Test city', 'from' => date('Y-m-d'), 'to' => date('Y-m-d')], 1);
Assert::count(20, $list['items']); Assert::true($list['pages'] >= 2);
$second = $read->demands(['status' => Demand::READY, 'locality' => 'Test city', 'from' => date('Y-m-d'), 'to' => date('Y-m-d')], 2);
Assert::same($list['count'], $second['count']);
Assert::notSame($list['items'][0]->getId(), $second['items'][0]->getId());
[$response, $html] = $run('Admin:Demand', ['action' => 'default', 'status' => Demand::READY, 'locality' => 'Test city', 'page' => 1]);
Assert::contains('page=2', html_entity_decode($html)); Assert::contains('status=ready', html_entity_decode($html));
$d->markNeedsReview('Test review'); $em->flush();
Assert::true($read->overview()['review'] > 0); Assert::true($read->demands(['issue' => 'review'], 1)['count'] > 0);

// A rejected operation on a closed demand keeps the manager usable and never reopens it.
$d->close(); $em->flush();
[$response, $html, $p] = $run('Admin:Demand', ['action' => 'detail', 'reference' => $d->getReference()]);
$requeuePost = ['category' => (string) $category->getId(), 'submit' => 'Uložiť', Form::ProtectorId => $p['requeueForm'][Form::ProtectorId]->getControl()->getAttribute('value')];
[$response, $html, $p] = $run('Admin:Demand', ['action' => 'detail', 'reference' => $d->getReference(), 'do' => 'requeueForm-submit'], 'POST', $requeuePost);
Assert::true($p['requeueForm']->hasErrors()); Assert::same(Demand::CLOSED, $d->getStatus()); Assert::true($em->isOpen());

// The admin login page remains publicly reachable; login and logout require valid CSRF.
$security->logout(true);
$admin->setPassword(\App\Model\Security\Passwords::create()->hash('admin-test-password')); $em->flush();
$security->setAuthenticator($auth);
[$response, $html, $p] = $run('Admin:Sign', ['action' => 'in']);
Assert::contains('Bripeon Admin', $html);
$loginPost = ['email' => $admin->getEmail(), 'password' => 'admin-test-password', 'remember' => '0', 'submit' => 'Prihlásiť'];
$run('Admin:Sign', ['action' => 'in', 'do' => 'loginForm-submit'], 'POST', $loginPost); Assert::false($security->isLoggedIn());
$loginPost[Form::ProtectorId] = $p['loginForm'][Form::ProtectorId]->getControl()->getAttribute('value');
$run('Admin:Sign', ['action' => 'in', 'do' => 'loginForm-submit'], 'POST', $loginPost); Assert::true($security->isAdmin());
[$response, $html, $p] = $run('Admin:Sign', ['action' => 'out']); Assert::true($security->isLoggedIn());
$run('Admin:Sign', ['action' => 'out', 'do' => 'logoutForm-submit'], 'POST', ['submit' => 'Odhlásiť']); Assert::true($security->isLoggedIn());
$run('Admin:Sign', ['action' => 'out', 'do' => 'logoutForm-submit'], 'POST', ['submit' => 'Odhlásiť', Form::ProtectorId => $p['logoutForm'][Form::ProtectorId]->getControl()->getAttribute('value')]);
Assert::false($security->isLoggedIn()); $security->login($admin->toIdentity());

$entries = $em->getRepository(AdminAudit::class)->findAll(); Assert::true(count($entries) >= 15);
foreach ($entries as $entry) {
    Assert::same($admin->getId(), $entry->getActor()->getId());
    Assert::notContains('token', json_encode($entry->getChanges()));
    Assert::notContains('password', json_encode($entry->getChanges()));
}
$security->logout(true);
echo "Admin account, catalogue, permissions, CSRF, history and pagination checks passed.\n";
