<?php

namespace App\UI\Control\Component\ProviderSignUpForm;

use App\Domain\CategoryService\CategoryService;
use App\Domain\City\City;
use App\Domain\Region\Region;
use Doctrine\ORM\EntityManagerInterface;
use Nette\Application\UI\Form;
use Nette\DI\Attributes\Inject;

class ProviderSignUpForm extends \Contributte\FormWizard\Wizard
{

	#[Inject]
	public EntityManagerInterface $entityManager;

	private array $stepNames = [
		1 => "Kategórie služieb",
		2 => "Konktatné údaje",
		3 => "Doplňujúce údaje",
	];

	protected function finish(): void
	{
		$values = $this->getValues();
	}

	protected function startup(): void
	{
		$this->skipStepIf(2, function (array $values): bool {
			return isset($values[1]) && $values[1]['skip'] === true;
		});
		$this->setDefaultValues(2, function (Form $form, array $values) {
			$data = [
				'username' => 'john_doe'
			];
			$form->setDefaults($data);
		});
	}

	public function getStepData(int $step): array
	{
		return [
			'name' => $this->stepNames[$step]
		];
	}

	protected function createStep1(): Form
	{
		$form = $this->createForm();

		$categories = $this->entityManager->getRepository(CategoryService::class)->findAll();
		$categoryOptions = [];
		foreach ($categories as $category) {
			$categoryOptions[$category->getId()] = $category->getName();
		}

		$form->addCheckboxList('serviceCategory', 'Typ služby', $categoryOptions)
			->setRequired('Prosím, vyberte aspoň jeden typ služby.');

		$regions = $this->entityManager->getRepository(Region::class)->findAll();
		$regionOptions = [];
		foreach ($regions as $region) {
			$regionOptions[$region->getId()] = $region->getName();
		}

		$form->addCheckboxList('region', 'Kraj', $regionOptions)
			->setRequired('Prosím, vyberte Váš kraj pôsobnosti.');

		$form->addSubmit(self::NEXT_SUBMIT_NAME, 'Ďalej');

		return $form;
	}

	protected function createStep2(): Form
	{
		$form = $this->createForm();

		$form->addEmail('email', 'Email')
			->setRequired('Prosím, zadajte Vašu platnú emailovú adresu.');

		$form->addText('phoneNumber', 'Telefónne číslo')
			->setRequired('Prosím, zadajte Vaše telefónne číslo.');

		$form->addSubmit(self::PREV_SUBMIT_NAME, 'Naspäť');
		$form->addSubmit(self::NEXT_SUBMIT_NAME, 'Ďalej');

		return $form;
	}

	protected function createStep3(): Form
	{
		$form = $this->createForm();

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

		$form->addPassword('password', 'Heslo')
			->setRequired('Prosím, zadajte heslo.');

		$form->addPassword('password2', 'Potvrdenie hesla')
			->setRequired('Prosím, potvrďte heslo.')
			->addRule($form::EQUAL, 'Heslá sa nezhodujú.', $form['password']);

		$form->addSubmit(self::PREV_SUBMIT_NAME, 'Naspäť');
		$form->addSubmit(self::NEXT_SUBMIT_NAME, 'Ďalej');

		return $form;
	}
}
