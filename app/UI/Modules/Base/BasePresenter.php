<?php declare(strict_types=1);

namespace App\UI\Modules\Base;

use App\Model\App;
use App\Model\Latte\TemplateProperty;
use App\Model\Security\SecurityUser;
use App\Settings;
use App\UI\Control\TFlashMessage;
use App\UI\Control\TModuleUtils;
use Contributte\Application\UI\Presenter\StructuredTemplates;
use Nette\Application\UI\Presenter;

/**
 * @property-read TemplateProperty $template
 * @property-read SecurityUser $user
 */
abstract class BasePresenter extends Presenter
{

	use StructuredTemplates;
	use TFlashMessage;
	use TModuleUtils;

	public function __construct(
		private readonly Settings $settings,
	)
	{
		parent::__construct();
	}

	public function beforeRender(): void
	{
		// Get the host from the HTTP request
		$host = $this->getHttpRequest()->getUrl()->getHost();

		// Check the current presenter and action to avoid infinite redirect
		// get access_granted setting from session
		$session = $this->getSession();
		$accessGranted = $session->get('access_granted');
		if ($host === $this->settings->testUrl && $accessGranted !== true) {
			// Redirect to the TestAuthPresenter:default action
			$this->redirect(App::DESTINATION_TEST_ACCESS);
		}
	}
}
