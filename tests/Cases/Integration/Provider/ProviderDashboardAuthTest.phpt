<?php declare(strict_types = 1);

namespace Tests\Cases\Integration\Provider;

use App\Bootstrap;
use Nette\Application\IPresenterFactory;
use Nette\Application\Request;
use Nette\Application\Responses\RedirectResponse;
use Tester\Assert;

require_once __DIR__ . '/../../../bootstrap.php';

$container = Bootstrap::boot()
	->addConfig(__DIR__ . '/../../../config/mailer.neon')
	->createContainer();

// An anonymous visitor to the provider dashboard is redirected (to provider sign-in).
test(function () use ($container): void {
	/** @var IPresenterFactory $presenterFactory */
	$presenterFactory = $container->getByType(IPresenterFactory::class);

	$presenter = $presenterFactory->createPresenter('Front:ProviderDashboard');
	$presenter->autoCanonicalize = false;

	$response = $presenter->run(new Request('Front:ProviderDashboard', 'GET', ['action' => 'default']));

	Assert::type(RedirectResponse::class, $response);
	Assert::contains('provider-sign/in', $response->getUrl());
});
