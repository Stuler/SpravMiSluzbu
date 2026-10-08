<?php declare(strict_types = 1);

namespace App\UI\Modules\Front;

use App\Model\App;
use App\UI\Form\BaseForm;
use App\UI\Form\FormFactory;
use App\UI\Modules\Base\UnsecuredPresenter;
use Nette\DI\Attributes\Inject;

abstract class BaseFrontPresenter extends UnsecuredPresenter
{

	#[Inject]
	public FormFactory $baseFormFactory;

	/**
	 * CSRF-protected provider logout. Available on every front page so the public
	 * navigation can render a logout action for authenticated providers.
	 */
	protected function createComponentProviderSignOutForm(): BaseForm
	{
		$form = $this->baseFormFactory->forFrontend();
		$form->addProtection('Vypršala platnosť bezpečnostného tokenu. Skúste to znova.');
		$form->addSubmit('submit', 'Odhlásiť sa');
		$form->onSuccess[] = [$this, 'processProviderSignOutForm'];

		return $form;
	}

	public function processProviderSignOutForm(BaseForm $form): void
	{
		if ($this->user->isLoggedIn()) {
			$this->user->logout(true);
			$this->flashSuccess('Boli ste odhlásený.');
		}

		$this->redirect(App::DESTINATION_FRONT_HOMEPAGE);
	}

}
