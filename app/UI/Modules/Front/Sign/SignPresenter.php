<?php declare(strict_types = 1);

namespace App\UI\Modules\Front\Sign;

use App\Domain\User\CreateUserFacade;
use App\Model\App;
use App\Model\Exception\Runtime\AuthenticationException;
use App\UI\Form\BaseForm;
use App\UI\Form\FormFactory;
use App\UI\Modules\Front\BaseFrontPresenter;
use Nette\DI\Attributes\Inject;

final class SignPresenter extends BaseFrontPresenter
{

	public string $backlink;

	#[Inject]
	public FormFactory $formFactory;

	#[Inject]
	public CreateUserFacade $createUserFacade;

	public function checkRequirements(mixed $element): void
	{
		// Disable auth
	}

	public function actionUp() {

	}

	/**
	 * Basic registration form
	 */
	public function createComponentSignUpForm(): BaseForm {

		$form = $this->formFactory->forFrontend();
		$form->addText('email', 'Email')
			->setRequired();
		$form->addText('name', 'Name')
			->setRequired();
		$form->addText('surname', 'Surname')
			->setRequired();
		$form->addText('street', 'Street')
			->setRequired();
		$form->addText('city', 'City')
			->setRequired();
		$form->addText('zipCode', 'Zip code')
			->setRequired();
		$form->addPassword('password', 'Password')
			->setRequired();
		$form->addPassword('password2', 'Password again')
			->setRequired();
		$form->addSubmit('submit', 'Sign up');

		$form->onSuccess[] = [$this, 'processSignUpForm'];

		return $form;
	}

	/**
	 * Process registration form
	 */
	public function processSignUpForm(BaseForm $form): void {
		$values = $form->getValues();
		try {
			$user = $this->createUserFacade->createUser((array) $values);
		} catch (\Exception $e) {
			$form->addError($e->getMessage());
			return;
		}
		$this->flashSuccess('You have been successfully registered');
		$this->redirect(App::DESTINATION_AFTER_SIGN_UP);
	}

	public function actionUpPro() {

	}

	public function createComponentFormSignUpPro() {

	}

	public function actionIn(): void
	{
		if ($this->user->isLoggedIn()) {
			$this->redirect(App::DESTINATION_AFTER_SIGN_IN);
		}
	}

	public function actionOut(): void
	{
		if ($this->user->isLoggedIn()) {
			$this->user->logout();
			$this->flashSuccess('_front.sign.out.success');
		}

		$this->redirect(App::DESTINATION_AFTER_SIGN_OUT);
	}

	public function processLoginForm(BaseForm $form): void
	{
		try {
			$this->user->setExpiration($form->values->remember ? '14 days' : '20 minutes');
			$this->user->login($form->values->email, $form->values->password);
		} catch (AuthenticationException $e) {
			$form->addError('Invalid username or password');

			return;
		}

		$this->redirect(App::DESTINATION_AFTER_SIGN_IN);
	}

	protected function createComponentSignInForm(): BaseForm
	{
		$form = $this->formFactory->forBackend();
		$form->addEmail('email')
			->setRequired();
		$form->addPassword('password')
			->setRequired();
		$form->addCheckbox('remember')
			->setDefaultValue(true);
		$form->addSubmit('submit');
		$form->onSuccess[] = [$this, 'processLoginForm'];

		return $form;
	}

}
