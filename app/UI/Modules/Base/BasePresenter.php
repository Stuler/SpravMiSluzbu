<?php declare(strict_types=1);

namespace App\UI\Modules\Base;

use App\Model\Latte\TemplateProperty;
use App\Model\Security\SecurityUser;
use App\UI\Control\TFlashMessage;
use App\UI\Control\TModuleUtils;
use Contributte\Application\UI\Presenter\StructuredTemplates;
use Nette\Application\UI\Presenter;
use Nette\Http\Session;

/**
 * @property-read TemplateProperty $template
 * @property-read SecurityUser $user
 */
abstract class BasePresenter extends Presenter
{

	use StructuredTemplates;
	use TFlashMessage;
	use TModuleUtils;

	public Session $session;

	protected function startup(): void
	{
		parent::startup();
		$this->session = $this->getSession();
	}
}
