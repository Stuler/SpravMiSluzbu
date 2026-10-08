<?php declare(strict_types=1);

namespace App\UI\Modules\Front\Demand;

use App\Domain\Demand\Demand;
use App\Domain\Demand\DemandFacade;
use App\UI\Form\BaseForm;
use App\UI\Modules\Front\CustomerBasePresenter;
use Nette\DI\Attributes\Inject;

final class DemandPresenter extends CustomerBasePresenter
{
	#[Inject]
	public DemandFacade $demands;
	#[Inject]
	public \App\Domain\Matching\InvitationFacade $invitations;
	private ?Demand $demand = null;

	public function actionCreate(): void
	{
		$section = $this->session->getSection('demandSubmission');
		if (!is_string($section['key'])) { $section['key'] = bin2hex(random_bytes(32)); }
		$section->setExpiration('2 hours');
	}

	public function actionSubmitted(): void
	{
		$reference = $this->session->getSection('demandSubmission')['receipt'];
		if (!is_string($reference)) { $this->redirect('create'); }
		$this->template->reference = $reference;
	}

	public function actionAccess(string $token = ''): void
	{
		$demand = $this->demands->findByToken($token);
		if ($demand === null) {
			$this->flashWarning('Odkaz je neplatný alebo vypršal. Požiadajte o nový.');
			$this->redirect('recover');
		}
		$section = $this->session->getSection('demandAccess');
		$section[$demand->getReference()] = hash('sha256', $token);
		$section->setExpiration('2 hours', $demand->getReference());
		// Remove the bearer token from the URL before rendering third-party assets.
		$this->redirect('detail', ['reference' => $demand->getReference()]);
	}

	public function actionDetail(string $reference): void
	{
		$demand = $this->demands->findByReference($reference);
		if ($demand === null || !$this->demands->canAccess($demand, $this->currentCustomer(), $this->accessHash($reference))) {
			$this->error('Dopyt nie je dostupný. Otvorte odkaz z e-mailu alebo sa prihláste.', 404);
		}
		$this->demand = $demand;
		$this->template->demand = $demand;
		$this->template->invitations = $this->invitations->forDemand($demand);
	}

	protected function createComponentDemandForm(): BaseForm
	{
		$form = $this->customerForm();
		$customer = $this->currentCustomer();
		$form->addHidden('submissionKey')->setDefaultValue($this->session->getSection('demandSubmission')['key']);
		$form->addTextArea('description', 'Čo potrebujete?')->setRequired('Opíšte požadovanú službu.')
			->addRule($form::MIN_LENGTH, 'Uveďte aspoň 15 znakov.', 15)->addRule($form::MAX_LENGTH, 'Opis môže mať najviac 5 000 znakov.', 5000)
			->setHtmlAttribute('rows', 6)->setHtmlAttribute('placeholder', 'Napríklad: Potrebujem vymaľovať dve izby v byte, spolu približne 35 m².');
		$form->addSelect('cityId', 'Obec alebo mesto', $this->demands->cityOptions())->setPrompt('Vyberte obec alebo mesto')->setRequired('Vyberte obec alebo mesto.');
		$form->addSelect('categoryId', 'Druh služby', $this->demands->categoryOptions())->setPrompt('Neviem zaradiť');
		$form->addText('requestedTerm', 'Kedy službu potrebujete?')->setRequired('Uveďte termín alebo „Podľa dohody“.')
			->addRule($form::MAX_LENGTH, 'Termín je príliš dlhý.', 255)->setHtmlAttribute('placeholder', 'Napríklad: budúci týždeň alebo podľa dohody');
		$form->addText('contactName', 'Vaše meno')->setRequired()->addRule($form::MAX_LENGTH, 'Meno je príliš dlhé.', 255)->setDefaultValue($customer?->getFullName());
		$email = $form->addEmail('contactEmail', 'E-mail')->setRequired()->addRule($form::MAX_LENGTH, 'E-mail je príliš dlhý.', 100);
		if ($customer !== null) { $email->setDefaultValue($customer->getEmail())->setDisabled(); }
		$form->addText('contactPhone', 'Telefón (nepovinný)')->addCondition($form::FILLED)
			->addRule($form::PATTERN, 'Zadajte platné telefónne číslo.', '[+0-9() .\-]{6,32}');
		$form->addSubmit('submit', 'Odoslať dopyt');
		$form->onSuccess[] = function (BaseForm $form): void {
			$v = (array) $form->getValues();
			$section = $this->session->getSection('demandSubmission');
			$key = (string) $section['key'];
			if ($key === '' || !hash_equals($key, (string) $v['submissionKey'])) {
				$form->addError('Formulár už bol odoslaný alebo vypršal. Obnovte stránku.');
				return;
			}
			$customer = $this->currentCustomer();
			if (!$this->allowRequest($form, 'demand-create', $customer?->getEmail() ?? (string) ($v['contactEmail'] ?? ''))) { return; }
			try {
				$demand = $this->demands->create($v, $customer, $key);
			} catch (\InvalidArgumentException $e) {
				$form->addError('Skontrolujte opis, obec, kategóriu a kontaktné údaje.');
				return;
			}
			unset($section['key']);
			$section['receipt'] = $demand->getReference();
			if ($customer !== null) { $this->redirect('detail', ['reference' => $demand->getReference()]); }
			$this->redirect('submitted');
		};
		return $form;
	}

	protected function createComponentVerifyForm(): BaseForm { return $this->statusForm(false); }
	protected function createComponentCloseForm(): BaseForm { return $this->statusForm(true); }

	private function statusForm(bool $close): BaseForm
	{
		$form = $this->customerForm();
		$form->addSubmit('submit', $close ? 'Uzavrieť dopyt' : 'Potvrdiť e-mail a odovzdať dopyt na spracovanie');
		$form->onSuccess[] = function (BaseForm $form) use ($close): void {
			if ($this->demand === null) { $this->error('Dopyt nie je dostupný.', 404); }
			$this->demands->changeStatus($this->demand, $this->currentCustomer(), $this->accessHash($this->demand->getReference()), $close);
			$this->flashSuccess($close ? 'Dopyt bol uzavretý.' : 'E-mail bol overený. Dopyt čaká na spracovanie.');
			$this->redirect('detail', ['reference' => $this->demand->getReference()]);
		};
		return $form;
	}

	protected function createComponentRecoverForm(): BaseForm
	{
		$form = $this->customerForm();
		$form->addText('reference', 'Číslo dopytu')->setRequired()->addRule($form::PATTERN, 'Skopírujte celé číslo dopytu.', '[a-f0-9]{32}');
		$form->addEmail('email', 'E-mail uvedený v dopyte')->setRequired();
		$form->addSubmit('submit', 'Poslať nový odkaz');
		$form->onSuccess[] = function (BaseForm $form): void {
			$v = $form->getValues();
			if (!$this->allowRequest($form, 'demand-recover', $v->email)) { return; }
			$this->demands->requestAccess($v->reference, $v->email);
			$this->flashSuccess('Ak údaje zodpovedajú dopytu bez účtu, poslali sme nový odkaz na jeho správu.');
			$this->redirect('recover');
		};
		return $form;
	}

	private function accessHash(string $reference): ?string
	{
		$value = $this->session->getSection('demandAccess')[$reference];
		return is_string($value) ? $value : null;
	}
}
