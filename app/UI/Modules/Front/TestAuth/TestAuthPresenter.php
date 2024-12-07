<?php

namespace App\UI\Modules\Front\TestAuth;

use App\Settings;
use App\UI\Form\BaseForm;
use App\UI\Form\FormFactory;
use JetBrains\PhpStorm\NoReturn;
use Nette\Application\UI\Presenter;
use Nette\DI\Attributes\Inject;

class TestAuthPresenter extends Presenter
{

	#[Inject]
	public FormFactory $formFactory;

	public function __construct(
		private readonly Settings $settings,
	)
	{
		parent::__construct();
	}

	public function renderDefault(): void
	{
		// set template
		$this->template->setFile(__DIR__ . '/templates/default.latte');
	}

	public function createComponentAuthTestForm(): BaseForm
	{
		$form = $this->formFactory->forFrontend();
		$form->addPassword('password', 'Password')
			->setRequired();
		$form->addSubmit('submit', 'Continue');

		$form->onSuccess[] = [$this, 'processAuthTestForm'];

		return $form;
	}

	#[NoReturn] public function processAuthTestForm(): void
	{
		$values = $this->getComponent('authTestForm')->getValues();
		$password = $values->password;

		// Check the password
		if ($password === $this->settings->testPassword) {
			$this->flashMessage('Password is correct.', 'success');
		} else {
			$this->flashMessage('Password is incorrect.', 'danger');
		}

		$this->redirectUrl($this->settings->testUrl);

	}

}
