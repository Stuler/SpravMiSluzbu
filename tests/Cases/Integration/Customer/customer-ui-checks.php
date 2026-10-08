<?php declare(strict_types=1);

// Loaded by CustomerDemandTest after its isolated database fixtures are prepared.
$httpRequest = new \Nette\Http\Request(new \Nette\Http\UrlScript('https://test.invalid/'));
$httpResponse = new \Nette\Http\Response();
$session = new \Nette\Http\Session($httpRequest, $httpResponse);
$session->setOptions(['useCookies' => false, 'useOnlyCookies' => false, 'cacheLimiter' => '', 'savePath' => sys_get_temp_dir()]);
$session->start();
$uiRateDirectory = sys_get_temp_dir() . '/bripeon-ui-rate-' . bin2hex(random_bytes(8));
$uiLimiter = new \App\Model\Security\RateLimit\PublicRateLimiter($uiRateDirectory);
$security->logout(true);
$latteFactory = new class implements \Nette\Bridges\ApplicationLatte\LatteFactory {
    public function create(): \Latte\Engine { $engine = new \Latte\Engine(); $engine->addFilter('json', [\App\Model\Latte\Filters::class, 'json']); return $engine; }
};
$templateFactory = new \Nette\Bridges\ApplicationLatte\TemplateFactory($latteFactory, $httpRequest, $security);
$templateFactory->onCreate[] = function ($template) use ($security): void { $template->_user = $security; };
$presenterFactory = new \Nette\Application\PresenterFactory();
$presenterFactory->setMapping(['Front' => ['App\UI\Modules\Front', '*', '*\*Presenter'], 'Admin' => ['App\UI\Modules\Admin', '*', '*\*Presenter']]);
$router = (new \App\Model\Router\RouterFactory())->create();
$run = function (string $name, array $parameters, string $method = 'GET', array $post = []) use ($presenterFactory, $router, $httpRequest, $httpResponse, $session, $security, $templateFactory, $accounts, $demands, $auth, $uiLimiter, $invitations, $em, $factory, $links, &$matching): array {
    $presenter = $presenterFactory->createPresenter($name);
    $request = $method === 'GET' ? $httpRequest : new \Nette\Http\Request(new \Nette\Http\UrlScript('https://test.invalid/'), $post, [], ['_nss' => '1'], ['sec-fetch-site' => 'same-origin'], $method);
    $presenter->injectPrimary($request, $httpResponse, $presenterFactory, $router, $session, $security, $templateFactory);
    if (property_exists($presenter, 'baseFormFactory')) { $presenter->baseFormFactory = new \App\UI\Form\FormFactory(); }
    if (property_exists($presenter, 'formFactory')) { $presenter->formFactory = new \App\UI\Form\FormFactory(); }
    if ($presenter instanceof \App\UI\Modules\Front\CustomerBasePresenter) {
        $presenter->accounts = $accounts;
        $presenter->rateLimiter = $uiLimiter;
    }
    if (property_exists($presenter, 'demands')) { $presenter->demands = $demands; }
    if (property_exists($presenter, 'invitations')) { $presenter->invitations = $invitations; }
    if (property_exists($presenter, 'matching')) { $presenter->matching = $matching; }
    $providerFacade = new \App\Domain\Provider\ProviderFacade($em, new \App\Model\Mail\MailSender($factory), $links);
    if ($presenter instanceof \App\UI\Modules\Front\ProviderDashboard\ProviderDashboardPresenter) { $presenter->providerFacade = $providerFacade; }
    if ($presenter instanceof \App\UI\Modules\Admin\BaseAdminPresenter) {
        $presenter->em = $em;
        $presenter->adminAudit = new \App\Domain\Admin\AdminAuditFacade($em, $security);
        $presenter->adminRead = new \App\Domain\Admin\AdminReadFacade($em, $presenter->adminAudit);
        if ($presenter instanceof \App\UI\Modules\Admin\AccountAdminPresenter) {
            $presenter->accounts = new \App\Domain\Admin\AdminAccountFacade($em, $presenter->adminAudit, $providerFacade, $accounts);
        }
        if ($presenter instanceof \App\UI\Modules\Admin\Provider\ProviderPresenter) { $presenter->providers = $providerFacade; }
        if ($presenter instanceof \App\UI\Modules\Admin\Demand\DemandPresenter) {
            $presenter->adminDemands = new \App\Domain\Admin\AdminDemandFacade($em, $presenter->adminAudit, $matching, $invitations);
        }
        if ($presenter instanceof \App\UI\Modules\Admin\CategoryService\CategoryServicePresenter) {
            $presenter->categoryServiceFacade = new \App\Domain\CategoryService\CategoryServiceFacade($em, $presenter->adminAudit);
        }
    }
    if (property_exists($presenter, 'customerAuthenticator')) { $presenter->customerAuthenticator = $auth; }
    $presenter->autoCanonicalize = false;
    $response = $presenter->run(new \Nette\Application\Request($name, $method, $parameters, $post));
    $html = $response instanceof \Nette\Application\Responses\TextResponse ? $response->getSource()->renderToString() : '';
    return [$response, $html, $presenter];
};

// Render real routes, including the shared layout and Nette forms.
foreach ([['Front:UserSign', 'up'], ['Front:UserSign', 'in'], ['Front:UserSign', 'forgot'], ['Front:UserSign', 'reset'], ['Front:UserSign', 'activateUser'], ['Front:Demand', 'create'], ['Front:Demand', 'recover']] as [$name, $action]) {
    [$response, $html] = $run($name, ['action' => $action]);
    \Tester\Assert::type(\Nette\Application\Responses\TextResponse::class, $response);
    \Tester\Assert::contains('<h1>', $html);
    \Tester\Assert::notContains('n:href', $html);
}
[$response] = $run('Front:CustomerDashboard', ['action' => 'default']);
\Tester\Assert::type(\Nette\Application\Responses\RedirectResponse::class, $response);
\Tester\Assert::contains('user-sign/in', $response->getUrl());

// A provider identity must not unlock a customer dashboard even with the same numeric ID.
$security->login(new \App\Model\Security\Identity($other->getId(), ['provider'], ['accountType' => 'provider']));
[$response] = $run('Front:CustomerDashboard', ['action' => 'default']);
\Tester\Assert::type(\Nette\Application\Responses\RedirectResponse::class, $response);
$security->logout(true);
\Tester\Assert::exception(fn() => $run('Front:Demand', ['action' => 'detail', 'reference' => $owned->getReference()]), \Nette\Application\BadRequestException::class);

// Missing CSRF token must not create a demand.
$count = $em->getRepository(\App\Domain\Demand\Demand::class)->count([]);
$post = array_replace($data, ['submissionKey' => $session->getSection('demandSubmission')['key'], 'submit' => 'Odoslať dopyt']);
[$response, $html, $postPresenter] = $run('Front:Demand', ['action' => 'create', 'do' => 'demandForm-submit'], 'POST', $post);
\Tester\Assert::same($count, $em->getRepository(\App\Domain\Demand\Demand::class)->count([]));
\Tester\Assert::true($postPresenter['demandForm']->hasErrors());
\Tester\Assert::contains('Toto pole je povinné.', $html);

// A valid CSRF-protected POST creates exactly one guest demand and redirects to its receipt.
[$response, $html, $getPresenter] = $run('Front:Demand', ['action' => 'create']);
$post = array_replace($data, ['categoryId' => '', 'submissionKey' => $session->getSection('demandSubmission')['key'], 'submit' => 'Odoslať dopyt',
    \Nette\Forms\Form::ProtectorId => $getPresenter['demandForm'][\Nette\Forms\Form::ProtectorId]->getControl()->getAttribute('value')]);
[$response] = $run('Front:Demand', ['action' => 'create', 'do' => 'demandForm-submit'], 'POST', $post);
\Tester\Assert::type(\Nette\Application\Responses\RedirectResponse::class, $response);
\Tester\Assert::contains('demand/submitted', $response->getUrl());
\Tester\Assert::same($count + 1, $em->getRepository(\App\Domain\Demand\Demand::class)->count([]));
[$response, $html] = $run('Front:Demand', ['action' => 'submitted']);
\Tester\Assert::contains('Dopyt je uložený', $html);

// An active customer's own dashboard renders; another customer's detail stays private.
$security->login($other->toIdentity());
[$response, $html] = $run('Front:CustomerDashboard', ['action' => 'default']);
\Tester\Assert::contains('Moje dopyty', $html);
\Tester\Assert::exception(fn() => $run('Front:Demand', ['action' => 'detail', 'reference' => $owned->getReference()]), \Nette\Application\BadRequestException::class);
$security->logout(true);

// An email link grants scoped access without verifying the demand on GET.
$guestForUi = $demands->create(array_replace($data, ['categoryId' => null]), null, bin2hex(random_bytes(32)));
$tokenForUi = $sender->token();
[$response] = $run('Front:Demand', ['action' => 'access', 'token' => $tokenForUi]);
\Tester\Assert::type(\Nette\Application\Responses\RedirectResponse::class, $response);
\Tester\Assert::notContains($tokenForUi, $response->getUrl());
[$response, $html] = $run('Front:Demand', ['action' => 'detail', 'reference' => $guestForUi->getReference()]);
\Tester\Assert::contains('Potvrdiť e-mail', $html);
\Tester\Assert::same(\App\Domain\Demand\Demand::PENDING, $guestForUi->getStatus());
\Tester\Assert::notContains($tokenForUi, $html);

// Verification and closing both require an actual POST with the form token.
[$response, $html, $detailPresenter] = $run('Front:Demand', ['action' => 'detail', 'reference' => $guestForUi->getReference()]);
$verifyPost = ['submit' => 'Potvrdiť', \Nette\Forms\Form::ProtectorId => $detailPresenter['verifyForm'][\Nette\Forms\Form::ProtectorId]->getControl()->getAttribute('value')];
[$response] = $run('Front:Demand', ['action' => 'detail', 'reference' => $guestForUi->getReference(), 'do' => 'verifyForm-submit'], 'POST', $verifyPost);
\Tester\Assert::type(\Nette\Application\Responses\RedirectResponse::class, $response);
\Tester\Assert::same(\App\Domain\Demand\Demand::READY, $guestForUi->getStatus());
[$response, $html, $detailPresenter] = $run('Front:Demand', ['action' => 'detail', 'reference' => $guestForUi->getReference()]);
$closePost = ['submit' => 'Uzavrieť', \Nette\Forms\Form::ProtectorId => $detailPresenter['closeForm'][\Nette\Forms\Form::ProtectorId]->getControl()->getAttribute('value')];
$run('Front:Demand', ['action' => 'detail', 'reference' => $guestForUi->getReference(), 'do' => 'closeForm-submit'], 'POST', $closePost);
\Tester\Assert::same(\App\Domain\Demand\Demand::CLOSED, $guestForUi->getStatus());

// Customer registration, activation, login and reset through real form handlers.
[$response, $html, $signPresenter] = $run('Front:UserSign', ['action' => 'up']);
$registerPost = ['name' => 'UI customer', 'email' => 'ui@example.test', 'password' => 'initial-password', 'password2' => 'initial-password', 'submit' => 'Vytvoriť účet',
    \Nette\Forms\Form::ProtectorId => $signPresenter['signUpForm'][\Nette\Forms\Form::ProtectorId]->getControl()->getAttribute('value')];
[$response] = $run('Front:UserSign', ['action' => 'up', 'do' => 'signUpForm-submit'], 'POST', $registerPost);
\Tester\Assert::type(\Nette\Application\Responses\RedirectResponse::class, $response);
$activation = $sender->token();
[$response] = $run('Front:UserSign', ['action' => 'activateUser', 'token' => $activation]);
\Tester\Assert::notContains($activation, $response->getUrl());
[$response, $html, $signPresenter] = $run('Front:UserSign', ['action' => 'activateUser']);
$activationPost = ['submit' => 'Aktivovať', \Nette\Forms\Form::ProtectorId => $signPresenter['activateForm'][\Nette\Forms\Form::ProtectorId]->getControl()->getAttribute('value')];
$run('Front:UserSign', ['action' => 'activateUser', 'do' => 'activateForm-submit'], 'POST', $activationPost);
[$response, $html, $signPresenter] = $run('Front:UserSign', ['action' => 'in']);
$loginPost = ['email' => 'ui@example.test', 'password' => 'initial-password', 'submit' => 'Prihlásiť',
    \Nette\Forms\Form::ProtectorId => $signPresenter['signInForm'][\Nette\Forms\Form::ProtectorId]->getControl()->getAttribute('value')];
[$response] = $run('Front:UserSign', ['action' => 'in', 'do' => 'signInForm-submit'], 'POST', $loginPost);
\Tester\Assert::contains('customer-dashboard', $response->getUrl());
\Tester\Assert::true($security->isCustomer());

// Forged guest contact in a logged-in submission cannot bypass account verification.
[$response, $html, $demandPresenter] = $run('Front:Demand', ['action' => 'create']);
$customerPost = array_replace($data, ['categoryId' => '', 'submissionKey' => $session->getSection('demandSubmission')['key'], 'submit' => 'Odoslať',
    \Nette\Forms\Form::ProtectorId => $demandPresenter['demandForm'][\Nette\Forms\Form::ProtectorId]->getControl()->getAttribute('value')]);
[$response] = $run('Front:Demand', ['action' => 'create', 'do' => 'demandForm-submit'], 'POST', $customerPost);
$customerDemand = $demands->findByReference($session->getSection('demandSubmission')['receipt']);
\Tester\Assert::same('ui@example.test', $customerDemand->getContactEmail());
\Tester\Assert::same(\App\Domain\Demand\Demand::READY, $customerDemand->getStatus());
$accounts->requestToken('ui@example.test', \App\Domain\Customer\CustomerToken::RESET);
$resetForUi = $sender->token();
[$response] = $run('Front:UserSign', ['action' => 'reset', 'token' => $resetForUi]);
\Tester\Assert::notContains($resetForUi, $response->getUrl());
[$response, $html, $signPresenter] = $run('Front:UserSign', ['action' => 'reset']);
$resetPost = ['password' => 'replacement-password', 'password2' => 'replacement-password', 'submit' => 'Uložiť',
    \Nette\Forms\Form::ProtectorId => $signPresenter['resetForm'][\Nette\Forms\Form::ProtectorId]->getControl()->getAttribute('value')];
$run('Front:UserSign', ['action' => 'reset', 'do' => 'resetForm-submit'], 'POST', $resetPost);
\Tester\Assert::false($security->isCustomer());
\Tester\Assert::same('customer', $auth->authenticate('ui@example.test', 'replacement-password')->getAccountType());

$session->close();
foreach (glob($uiRateDirectory . '/*.json') ?: [] as $file) { unlink($file); }
if (is_dir($uiRateDirectory)) { rmdir($uiRateDirectory); }

