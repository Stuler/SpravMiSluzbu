<?php declare(strict_types=1);

use App\UI\Form\FormFactory;
use App\UI\Modules\Front\ProviderSign\ProviderSignPresenter;
use Nette\Application\AbortException;
use Nette\Application\Responses\JsonResponse;
use Nette\Application\UI\Presenter;
use Nette\Forms\Controls\CsrfProtection;
use Nette\Http\Request;
use Nette\Http\Response;
use Nette\Http\Session;
use Nette\Http\UrlScript;
use Tester\Assert;

require_once __DIR__ . '/../../../bootstrap.php';

test(function (): void {
	$request = new Request(new UrlScript('http://localhost/provider-sign/up'), headers: ['X-Requested-With' => 'XMLHttpRequest'], method: 'POST');
	$response = new Response();
	$session = new Session($request, $response);
	$presenter = new ProviderSignPresenter();
	$presenter->injectPrimary($request, $response, session: $session);
	$presenter->formFactory = new FormFactory();
	$form = (new ReflectionMethod($presenter, 'createComponentRegistrationForm'))->invoke($presenter);
	Assert::type(CsrfProtection::class, $form['_token_']);
	$form['_token_']->session = $session;
	(new ReflectionProperty(Nette\Forms\Form::class, 'httpData'))->setValue($form, []);
	$form->setSubmittedBy($form['submit']);

	// Missing CSRF must reach onError and return JSON, never register an account.
	Assert::exception(fn() => $form->fireEvents(), AbortException::class);
	Assert::same(422, $response->getCode());
	$json = (new ReflectionProperty(Presenter::class, 'response'))->getValue($presenter);
	Assert::type(JsonResponse::class, $json);
	Assert::same(['errors' => $form->getErrors()], $json->getPayload());
	Assert::true(count($form->getErrors()) > 0);
});

test(function (): void {
	$request = new Request(new UrlScript('http://localhost/provider-sign/up'), headers: ['X-Requested-With' => 'XMLHttpRequest']);
	$response = new Response();
	$presenter = new ProviderSignPresenter();
	$presenter->injectPrimary($request, $response);
	$presenter->formFactory = new FormFactory();
	$form = (new ReflectionMethod($presenter, 'createComponentRegistrationForm'))->invoke($presenter);
	$form->addError('Registration failed.');
	(new ReflectionProperty(Nette\Forms\Form::class, 'httpData'))->setValue($form, []);
	$form->setSubmittedBy($form['submit']);
	Assert::exception(fn() => $form->fireEvents(), AbortException::class);
	$json = (new ReflectionProperty(Presenter::class, 'response'))->getValue($presenter);
	Assert::same(['errors' => ['Registration failed.']], $json->getPayload());
});

test(function (): void {
	$request = new Request(new UrlScript('http://localhost/provider-sign/up'));
	$presenter = new ProviderSignPresenter();
	$presenter->injectPrimary($request, new Response());
	$presenter->formFactory = new FormFactory();
	$form = (new ReflectionMethod($presenter, 'createComponentRegistrationForm'))->invoke($presenter);
	$form->addError('Registration failed.');
	(new ReflectionProperty(Nette\Forms\Form::class, 'httpData'))->setValue($form, []);
	$form->setSubmittedBy($form['submit']);
	Assert::noError(fn() => $form->fireEvents());
	Assert::same(['Registration failed.'], $form->getErrors());
});
