<?php declare(strict_types = 1);

namespace App\UI\Modules\Admin\Sign;

use App\Model\App;
use App\Model\Exception\Runtime\AuthenticationException;
use App\UI\Form\BaseForm;
use App\UI\Form\FormFactory;
use App\UI\Modules\Base\UnsecuredPresenter;

final class SignPresenter extends UnsecuredPresenter
{

	/** @var string @persistent */
	public string $backlink;

	/** @var FormFactory @inject */
	public FormFactory $formFactory;

	public function checkRequirements(mixed $element): void
	{
		// Disable auth
	}

	public function actionIn(): void
	{
		if ($this->user->isLoggedIn()) {
			$this->redirect(App::DESTINATION_AFTER_SIGN_IN);
		}
	}

	public function actionOut(): void
	{
		if (!$this->user->isLoggedIn()) { $this->redirect(App::DESTINATION_AFTER_SIGN_OUT); }
	}
	protected function startup(): void
	{
		parent::startup(); $this->session->start();
		$this->getHttpResponse()->setHeader('Cache-Control', 'no-store');
	}
	protected function createComponentLogoutForm(): BaseForm
	{
		$form = $this->formFactory->forBackend();
		$form->addProtection('Platnosť formulára vypršala.');
		$form->addSubmit('submit', 'Odhlásiť sa');
		$form->onSuccess[] = function (): void {
			$this->user->logout(true); $this->redirect(App::DESTINATION_AFTER_SIGN_OUT);
		};
		return $form;
	}

	public function processLoginForm(BaseForm $form): void
	{
		try {
			$this->user->setExpiration($form->values->remember ? '14 days' : '20 minutes');
			$this->user->login($form->values->email, $form->values->password);
			if (!$this->user->isAdmin()) {
				$this->user->logout(true); $form->addError('Účet nemá oprávnenie na prístup do administrácie.'); return;
			}
		} catch (AuthenticationException $e) {
			$form->addError('Nesprávne prihlasovacie údaje alebo neaktívny účet.');

			return;
		}

		$this->redirect(App::DESTINATION_AFTER_SIGN_IN);
	}

	protected function createComponentLoginForm(): BaseForm
	{
		$form = $this->formFactory->forBackend();
		$form->addProtection('Platnosť formulára vypršala.');
		$form->addEmail('email')
			->setRequired(true);
		$form->addPassword('password')
			->setRequired(true);
		$form->addCheckbox('remember')
			->setDefaultValue(true);
		$form->addSubmit('submit');
		$form->onSuccess[] = [$this, 'processLoginForm'];

		return $form;
	}

}
