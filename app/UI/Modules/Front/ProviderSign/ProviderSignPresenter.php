<?php declare(strict_types=1);

namespace App\UI\Modules\Front\ProviderSign;

use App\Domain\Provider\DTO\ProviderRegistrationDataFactory;
use App\Domain\Provider\Exception\DuplicateEmailException;
use App\Domain\Provider\Exception\InvalidActivationTokenException;
use App\Domain\Provider\Exception\ProviderBlockedException;
use App\Domain\Provider\Exception\ProviderNotActivatedException;
use App\Domain\Provider\Exception\RegistrationValidationException;
use App\Domain\Provider\ProviderFacade;
use App\Model\App;
use App\Model\Exception\Runtime\AuthenticationException;
use App\Model\Security\Authenticator\ProviderAuthenticator;
use App\UI\Form\BaseForm;
use App\UI\Form\FormFactory;
use App\UI\Modules\Front\BaseFrontPresenter;
use Nette\DI\Attributes\Inject;
use Tracy\Debugger;
use Tracy\ILogger;

final class ProviderSignPresenter extends BaseFrontPresenter
{

	#[Inject]
	public FormFactory $formFactory;

	#[Inject]
	public ProviderFacade $providerFacade;

	#[Inject]
	public ProviderAuthenticator $providerAuthenticator;

	public function checkRequirements(mixed $element): void
	{
		// Public pages: registration, activation and sign-in are intentionally unsecured.
	}

	protected function startup(): void
	{
		parent::startup();

		// CSRF-protected forms need the session available before the (large) layout
		// flushes output, otherwise the session cannot be started mid-render.
		if (!$this->session->isStarted()) {
			$this->session->start();
		}
	}

	// === Registration =======================================================

	public function actionUp(): void
	{
		if ($this->user->isProvider()) {
			$this->redirect(App::DESTINATION_PROVIDER_DASHBOARD);
		}
	}

	/**
	 * CSRF-protected Nette form wrapper for the React registration widget.
	 * The widget serializes its collected data into the hidden `payload` field.
	 */
	protected function createComponentRegistrationForm(): BaseForm
	{
		$form = $this->formFactory->forFrontend();
		$form->addProtection('Vypršala platnosť bezpečnostného tokenu. Načítajte stránku znova a skúste to ešte raz.');
		$form->addHidden('payload');
		$form->addSubmit('submit', 'Zaregistrovať sa');
		$form->onSuccess[] = [$this, 'processRegistrationForm'];
		$form->onError[] = [$this, 'processRegistrationErrors'];

		return $form;
	}

	public function processRegistrationErrors(BaseForm $form): void
	{
		if ($this->isAjax()) {
			$this->getHttpResponse()->setCode(422);
			$this->sendJson(['errors' => $form->getErrors()]);
		}
	}

	public function processRegistrationForm(BaseForm $form): void
	{
		$payload = (string) $form->getValues()->payload;
		$data = json_decode($payload, true);
		if (!is_array($data)) {
			$form->addError('Formulár sa nepodarilo spracovať. Skúste to znova.');

			return;
		}

		$dto = ProviderRegistrationDataFactory::fromArray($data);

		try {
			$result = $this->providerFacade->register($dto);
		} catch (DuplicateEmailException $e) {
			$form->addError('Poskytovateľ s touto e-mailovou adresou už existuje. Skúste sa prihlásiť.');

			return;
		} catch (RegistrationValidationException $e) {
			$form->addError('Zadané údaje nie sú úplné alebo platné. Skontrolujte formulár a skúste to znova.');

			return;
		} catch (\Throwable $e) {
			Debugger::log($e, ILogger::EXCEPTION);
			$form->addError('Pri registrácii nastala neočakávaná chyba. Skúste to znova neskôr.');

			return;
		}

		$section = $this->session->getSection('providerRegistration');
		$section->set('email', $result->email);
		$section->set('mailSent', $result->activationMailSent);

		$this->redirect('registered');
	}

	public function actionRegistered(): void
	{
		$section = $this->session->getSection('providerRegistration');
		$email = $section->get('email');
		$mailSent = $section->get('mailSent');

		if (!is_string($email)) {
			// Direct visit without a completed registration.
			$this->redirect('up');
		}

		$this->template->registeredEmail = $email;
		$this->template->mailSent = $mailSent !== false;

		$this['resendActivationForm']->setDefaults(['email' => $email]);
	}

	// === Activation =========================================================

	/**
	 * Branded confirmation page opened from the activation email.
	 * GET must not mutate any account state.
	 */
	public function actionActivate(string $token = ''): void
	{
		$this->getHttpResponse()->setHeader('Referrer-Policy', 'no-referrer');

		$provider = $this->providerFacade->findFreshProviderByRawToken($token);
		$this->template->tokenValid = $provider !== null;

		if ($provider !== null) {
			$this['confirmActivationForm']->setDefaults(['token' => $token]);
		}
	}

	protected function createComponentConfirmActivationForm(): BaseForm
	{
		$form = $this->formFactory->forFrontend();
		$form->addProtection('Vypršala platnosť bezpečnostného tokenu. Otvorte odkaz z e-mailu znova.');
		$form->addHidden('token');
		$form->addSubmit('submit', 'Aktivovať a prihlásiť sa');
		$form->onSuccess[] = [$this, 'processConfirmActivationForm'];

		return $form;
	}

	public function processConfirmActivationForm(BaseForm $form): void
	{
		$token = (string) $form->getValues()->token;

		try {
			$provider = $this->providerFacade->activate($token);
		} catch (InvalidActivationTokenException $e) {
			$this->flashError('Aktivačný odkaz je neplatný alebo jeho platnosť vypršala. Nechajte si poslať nový.');
			$this->redirect('activate', ['token' => '']);
		}

		// Fresh session for the newly authenticated provider, then sign in.
		$this->session->regenerateId();
		$this->user->setExpiration('20 minutes');
		$this->user->login($provider->toIdentity());

		$this->flashSuccess('Váš účet bol aktivovaný a ste prihlásený.');
		$this->redirect(App::DESTINATION_PROVIDER_DASHBOARD);
	}

	// === Resend activation ==================================================

	protected function createComponentResendActivationForm(): BaseForm
	{
		$form = $this->formFactory->forFrontend();
		$form->addProtection('Vypršala platnosť bezpečnostného tokenu. Skúste to znova.');
		$form->addEmail('email', 'E-mail')
			->setRequired('Zadajte e-mailovú adresu.');
		$form->addSubmit('submit', 'Poslať nový aktivačný odkaz');
		$form->onSuccess[] = [$this, 'processResendActivationForm'];

		return $form;
	}

	public function processResendActivationForm(BaseForm $form): void
	{
		$this->providerFacade->resendActivation((string) $form->getValues()->email);

		// Always generic to avoid revealing whether the account exists.
		$this->flashSuccess('Ak pre zadanú adresu existuje neaktivovaný účet, poslali sme naň nový aktivačný odkaz.');
		$this->redirect('this');
	}

	// === Sign in ============================================================

	public function actionIn(): void
	{
		if ($this->user->isProvider()) {
			$this->redirect(App::DESTINATION_PROVIDER_DASHBOARD);
		}
	}

	protected function createComponentSignInForm(): BaseForm
	{
		$form = $this->formFactory->forFrontend();
		$form->addProtection('Vypršala platnosť bezpečnostného tokenu. Skúste to znova.');
		$form->addEmail('email', 'E-mail')
			->setRequired('Zadajte e-mailovú adresu.');
		$form->addPassword('password', 'Heslo')
			->setRequired('Zadajte heslo.');
		$form->addCheckbox('remember', 'Zapamätať si ma')
			->setDefaultValue(false);
		$form->addSubmit('submit', 'Prihlásiť sa');
		$form->onSuccess[] = [$this, 'processSignInForm'];

		return $form;
	}

	public function processSignInForm(BaseForm $form): void
	{
		$values = $form->getValues();

		try {
			$identity = $this->providerAuthenticator->authenticate((string) $values->email, (string) $values->password);
		} catch (ProviderNotActivatedException $e) {
			$form->addError('Váš účet ešte nie je aktivovaný. Skontrolujte si e-mail alebo si nechajte poslať nový aktivačný odkaz.');

			return;
		} catch (ProviderBlockedException $e) {
			$form->addError('Váš účet je zablokovaný. Kontaktujte podporu.');

			return;
		} catch (AuthenticationException $e) {
			$form->addError('Nesprávny e-mail alebo heslo.');

			return;
		}

		$this->session->regenerateId();
		$this->user->setExpiration($values->remember ? '14 days' : '20 minutes');
		$this->user->login($identity);

		$this->redirect(App::DESTINATION_PROVIDER_DASHBOARD);
	}

}
