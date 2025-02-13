<?php declare(strict_types=1);

namespace App\UI\Modules\Front\ProviderSign;

use App\Domain\CategoryService\CategoryService;
use App\Domain\City\City;
use App\Domain\Provider\CreateProviderFacade;
use App\Domain\Region\Region;
use App\Model\App;
use App\Model\Exception\Runtime\AuthenticationException;
use App\UI\Control\Component\ProviderSignUpForm\ProviderSignUpFormComp;
use App\UI\Form\BaseForm;
use App\UI\Form\FormFactory;
use App\UI\Modules\Front\BaseFrontPresenter;
use Doctrine\ORM\EntityManagerInterface;
use Nette\DI\Attributes\Inject;

final class ProviderSignPresenter extends BaseFrontPresenter
{

	public string $backlink;

	#[Inject]
	public FormFactory $formFactory;

	#[Inject]
	public CreateProviderFacade $createProviderFacade;

	#[Inject]
	public EntityManagerInterface $entityManager;

	#[Inject]
	public ProviderSignUpFormComp $providerSignUpFormComp;

	public function checkRequirements(mixed $element): void
	{
		// Disable auth
	}

	public function actionUp()
	{

	}

	/**
	 * Basic registration form for service provider
	 */
	public function createComponentSignUpForm(): BaseForm
	{
		$form = $this->formFactory->forFrontend();


		$form->addText('contactName', 'Meno')
			->setRequired('Prosím, zadajte Vaše kontaktnej osoby.');

		$form->addText('contactSurname', 'Priezvisko')
			->setRequired('Prosím, zadajte Vaše priezvisko.');

		$form->addText('contactTitle', 'Titul');

		$form->addEmail('email', 'Email')
			->setRequired('Prosím, zadajte Vašu platnú emailovú adresu.');

		$form->addText('phoneNumber', 'Telefónne číslo')
			->setRequired('Prosím, zadajte Vaše telefónne číslo.');

		$form->addText('companyName', 'Názov spoločnosti')
			->setRequired('Prosím, zadajte názov vašej spoločnosti.');

		$form->addText('ico', 'IČO');

		$form->addText('dic', 'DIČ');

		$form->addText('streetNo', 'Ulica')
			->setRequired('Prosím, zadajte Vašu ulicu.');

		$cities = $this->entityManager->getRepository(City::class)->findAll();
		$cityOptions = [];
		foreach ($cities as $city) {
			$cityOptions[$city->getId()] = $city->getFullname();
		}

		$form->addSelect('city', 'Obec', $cityOptions)
			->setRequired('Prosím, vyberte Vašu obec.');

		$form->addText('zipCode', 'PSČ')
			->setRequired('Prosím, zadajte Vaše PSČ.');

		$regions = $this->entityManager->getRepository(Region::class)->findAll();
		$regionOptions = [];
		foreach ($regions as $region) {
			$regionOptions[$region->getId()] = $region->getName();
		}

		$form->addCheckboxList('region', 'Kraj', $regionOptions)
			->setRequired('Prosím, vyberte Váš kraj pôsobnosti.');

		$categories = $this->entityManager->getRepository(CategoryService::class)->findAll();
		$categoryOptions = [];
		foreach ($categories as $category) {
			$categoryOptions[$category->getId()] = $category->getName();
		}

		$form->addCheckboxList('serviceCategory', 'Typ služby', $categoryOptions)
			->setRequired('Prosím, vyberte aspoň jeden typ služby.');

		$form->addPassword('password', 'Heslo')
			->setRequired('Prosím, zadajte heslo.');

		$form->addPassword('password2', 'Potvrdenie hesla')
			->setRequired('Prosím, potvrďte heslo.')
			->addRule($form::EQUAL, 'Heslá sa nezhodujú.', $form['password']);

		$form->addSubmit('submit', 'Zaregistrovať sa');

		$form->onSuccess[] = [$this, 'processSignUpForm'];

		return $form;
	}

	public function handleChangeStep(int $step): void
	{
		$this['wizard']->setStep($step);

		$this->redirect('wizard'); // Optional, hides parameter from URL
	}

	protected function createComponentWizard(): ProviderSignUpFormComp
	{
		return $this->providerSignUpFormComp;
	}

	/**
	 * Process registration form
	 */
	public function processSignUpForm(BaseForm $form): void
	{
		$values = $form->getValues();
		try {
			$user = $this->createProviderFacade->createProvider((array)$values);
		} catch (\Exception $e) {
			$form->addError($e->getMessage());
			return;
		}
		$this->flashSuccess('You have been successfully registered');
		$this->redirect(App::DESTINATION_AFTER_SIGN_UP_USER);
	}

	public function actionUpPro()
	{

	}

	public function createComponentFormSignUpPro()
	{

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
