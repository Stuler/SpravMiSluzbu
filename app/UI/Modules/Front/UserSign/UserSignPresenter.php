<?php declare(strict_types=1);

namespace App\UI\Modules\Front\UserSign;

use App\Domain\Customer\CustomerToken;
use App\Model\Security\Authenticator\UserAuthenticator;
use App\UI\Form\BaseForm;
use App\UI\Modules\Front\CustomerBasePresenter;
use Nette\DI\Attributes\Inject;

final class UserSignPresenter extends CustomerBasePresenter
{
	#[Inject]
	public UserAuthenticator $customerAuthenticator;

	public function actionIn(): void
	{
		if ($this->currentCustomer() !== null) { $this->redirect(':Front:CustomerDashboard:default'); }
	}

	public function actionOut(): void
	{
		// Logout is handled by the CSRF-protected form in the public navigation.
		$this->redirect(':Front:Home:default');
	}

	public function actionActivateUser(?string $token = null): void { $this->prepareToken(CustomerToken::ACTIVATE, $token); }
	public function actionReset(?string $token = null): void { $this->prepareToken(CustomerToken::RESET, $token); }

	private function prepareToken(string $purpose, ?string $token): void
	{
		$section = $this->session->getSection('customerTokens');
		if ($token !== null) {
			$section[$purpose] = $this->accounts->isValidToken($token, $purpose) ? $token : null;
			$section->setExpiration('1 hour');
			$this->redirect($purpose === CustomerToken::ACTIVATE ? 'activateUser' : 'reset');
		}
		$this->template->tokenValid = $this->accounts->isValidToken((string) $section[$purpose], $purpose);
	}

	protected function createComponentSignUpForm(): BaseForm
	{
		$form = $this->customerForm();
		$form->addText('name', 'Meno')->setRequired('Zadajte svoje meno.')->addRule($form::MAX_LENGTH, 'Meno je príliš dlhé.', 255);
		$form->addEmail('email', 'E-mail')->setRequired('Zadajte e-mail.')->addRule($form::MAX_LENGTH, 'E-mail je príliš dlhý.', 100);
		$this->addPasswords($form);
		$form->addSubmit('submit', 'Vytvoriť účet');
		$form->onSuccess[] = function (BaseForm $form): void {
			$v = $form->getValues();
			if (!$this->allowRequest($form, 'customer-register', $v->email)) { return; }
			try {
				$this->accounts->register($v->name, $v->email, $v->password);
			} catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
				// Identical response for concurrent or existing registrations.
			} catch (\Throwable $e) {
				\Tracy\Debugger::log('Customer registration or activation mail failed.', 'error');
				$form->addError('Registráciu sa nepodarilo dokončiť. Skúste to znova alebo požiadajte o nový aktivačný e-mail.');
				return;
			}
			$this->redirect('registered');
		};
		return $form;
	}

	protected function createComponentSignInForm(): BaseForm
	{
		$form = $this->customerForm();
		$form->addEmail('email', 'E-mail')->setRequired();
		$form->addPassword('password', 'Heslo')->setRequired();
		$form->addSubmit('submit', 'Prihlásiť sa');
		$form->onSuccess[] = function (BaseForm $form): void {
			$v = $form->getValues();
			if (!$this->allowRequest($form, 'customer-login', $v->email)) { return; }
			try {
				$identity = $this->customerAuthenticator->authenticate($v->email, $v->password);
				if (!$identity instanceof \App\Model\Security\Identity || $identity->getAccountType() !== \App\Model\Security\Identity::ACCOUNT_TYPE_CUSTOMER) {
					$form->addError('Nesprávne údaje alebo neaktivovaný zákaznícky účet.');
					return;
				}
				$this->user->setExpiration('2 hours');
				$this->user->login($identity);
			} catch (\Nette\Security\AuthenticationException $e) {
				$form->addError('Nesprávne údaje alebo neaktivovaný zákaznícky účet.');
				return;
			}
			$this->redirect(':Front:CustomerDashboard:default');
		};
		return $form;
	}

	protected function createComponentResendActivationForm(): BaseForm { return $this->tokenRequestForm(CustomerToken::ACTIVATE); }
	protected function createComponentForgotForm(): BaseForm { return $this->tokenRequestForm(CustomerToken::RESET); }

	private function tokenRequestForm(string $purpose): BaseForm
	{
		$form = $this->customerForm();
		$form->addEmail('email', 'E-mail')->setRequired();
		$form->addSubmit('submit', 'Poslať odkaz');
		$form->onSuccess[] = function (BaseForm $form) use ($purpose): void {
			$email = $form->getValues()->email;
			if (!$this->allowRequest($form, 'customer-token-' . $purpose, $email)) { return; }
			try {
				$this->accounts->requestToken($email, $purpose);
			} catch (\Throwable $e) {
				\Tracy\Debugger::log('Customer token mail failed.', 'error');
			}
			$this->flashSuccess('Ak existuje zodpovedajúci účet, poslali sme vám e-mail s ďalším postupom.');
			$this->redirect('in');
		};
		return $form;
	}

	protected function createComponentActivateForm(): BaseForm
	{
		$form = $this->customerForm();
		$form->addSubmit('submit', 'Potvrdiť e-mail a aktivovať účet');
		$form->onSuccess[] = function (BaseForm $form): void {
			try {
				$this->accounts->activate((string) $this->session->getSection('customerTokens')[CustomerToken::ACTIVATE]);
			} catch (\InvalidArgumentException $e) {
				$form->addError('Odkaz je neplatný alebo už vypršal. Požiadajte o nový aktivačný e-mail.');
				return;
			}
			unset($this->session->getSection('customerTokens')[CustomerToken::ACTIVATE]);
			$this->flashSuccess('Účet je aktivovaný. Môžete sa prihlásiť.');
			$this->redirect('in');
		};
		return $form;
	}

	protected function createComponentResetForm(): BaseForm
	{
		$form = $this->customerForm();
		$this->addPasswords($form);
		$form->addSubmit('submit', 'Uložiť nové heslo');
		$form->onSuccess[] = function (BaseForm $form): void {
			try {
				$this->accounts->resetPassword((string) $this->session->getSection('customerTokens')[CustomerToken::RESET], $form->getValues()->password);
			} catch (\InvalidArgumentException $e) {
				$form->addError('Heslo alebo odkaz nie je platný. Skontrolujte heslo alebo požiadajte o nový odkaz.');
				return;
			}
			unset($this->session->getSection('customerTokens')[CustomerToken::RESET]);
			if ($this->user->isCustomer()) { $this->user->logout(true); }
			$this->flashSuccess('Heslo bolo zmenené. Prihláste sa novým heslom.');
			$this->redirect('in');
		};
		return $form;
	}

	private function addPasswords(BaseForm $form): void
	{
		$form->addPassword('password', 'Heslo')->setRequired()->addRule($form::MIN_LENGTH, 'Použite aspoň 10 znakov.', 10)
			->addRule(static fn($control): bool => strlen($control->getValue()) <= 72, 'Heslo je príliš dlhé. Skráťte ho, prosím.');
		$form->addPassword('password2', 'Heslo znovu')->setRequired()->addRule($form::EQUAL, 'Heslá sa nezhodujú.', $form['password']);
	}
}
