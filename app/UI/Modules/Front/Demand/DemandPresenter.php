<?php declare(strict_types=1);

namespace App\UI\Modules\Front\Demand;

use App\Domain\CategoryService\CategoryCatalogue;
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
	#[Inject] public \App\Domain\Job\JobFacade $jobs;
	#[Inject] public \App\Domain\Review\ReviewFacade $reviews;
	private ?\App\Domain\Job\Job $job = null;
	private ?Demand $demand = null;
	private ?int $categoryHint = null;

	/** @param string|null $categoryId Optional service preselected from a catalogue link; invalid values are ignored. */
	public function actionCreate(?string $categoryId = null): void
	{
		$this->categoryHint = $categoryId !== null && ctype_digit($categoryId) ? (int) $categoryId : null;
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
		$this->job = $this->jobs->current($demand);
		$this->template->job = $this->job;
		$this->template->jobs = $this->jobs->forDemand($demand);
		$this->template->review = $this->job ? $this->reviews->forJob($this->job) : null;
		$this->template->canReview = $this->job && $this->reviews->canReview($this->job, $this->currentCustomer(), $this->accessHash($reference));
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
		$groups = $this->demands->categoryGroups();
		$category = $form->addSelect('categoryId', 'Druh služby', CategoryCatalogue::selectOptions($groups))->setPrompt('Neviem zaradiť');
		if ($this->categoryHint !== null && isset(array_replace([], ...array_column($groups, 'categories'))[$this->categoryHint])) {
			$category->setDefaultValue($this->categoryHint);
		}
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

	    protected function createComponentSelectProviderForm(): \Nette\Application\UI\Multiplier
    {
        return new \Nette\Application\UI\Multiplier(function (string $id): BaseForm {
            $form = $this->customerForm(); $form->addSubmit('submit', 'Dohodli sme sa');
            $form->onSuccess[] = function (BaseForm $form) use ($id): void {
                try { $this->jobs->select($this->demand, (int) $id, $this->currentCustomer(), $this->accessHash($this->demand->getReference())); }
                catch (\InvalidArgumentException $e) { $form->addError('Poskytovateľa nemožno vybrať. Obnovte stránku a skontrolujte aktuálnu realizáciu.'); return; }
                $this->redirect('this');
            }; return $form;
        });
    }
    protected function createComponentCancelJobForm(): BaseForm
    {
        $form = $this->customerForm(); $form->addSubmit('submit', 'Zrušiť dohodu a vybrať náhradu');
        $form->onSuccess[] = function (BaseForm $form): void {
            if ($this->job === null) { $form->addError('Realizácia už nie je aktuálna.'); return; }
            try { $this->jobs->cancel($this->job->getId(), $this->currentCustomer(), $this->accessHash($this->demand->getReference())); }
            catch (\InvalidArgumentException $e) { $form->addError('Dohodu už nemožno zrušiť.'); return; }
            $this->redirect('this');
        }; return $form;
    }
    protected function createComponentWorkForm(): BaseForm
    {
        $form = $this->customerForm();
        $form->addRadioList('outcome', 'Ako prebiehajú práce?', ['completed' => 'Práce prebehli', 'partial' => 'Práce prebehli čiastočne', 'ongoing' => 'Práce ešte prebiehajú', 'not_started' => 'Práce sa nezačali'])->setRequired();
        $form->addText('date', 'Dátum vykonaných prác')->setHtmlType('date');
        $form->addTextArea('description', 'Stručný opis vykonaných prác')->addRule($form::MAX_LENGTH, 'Najviac 1 000 znakov.', 1000);
        $form->addSubmit('submit', 'Uložiť výsledok');
        $form->onSuccess[] = function (BaseForm $form): void {
            if ($this->job === null) { $form->addError('Realizácia už nie je aktuálna.'); return; }
            $v = $form->getValues();
            try { $this->jobs->report($this->job->getId(), $this->currentCustomer(), $this->accessHash($this->demand->getReference()), $v->outcome, $v->date, $v->description); }
            catch (\InvalidArgumentException $e) { $form->addError('Pri vykonaných prácach uveďte platný dátum, ktorý nie je v budúcnosti, a stručný opis.'); return; }
            $this->flashSuccess('Výsledok realizácie je uložený.'); $this->redirect('this');
        }; return $form;
    }
    protected function createComponentReviewForm(): BaseForm
    {
        $form = $this->customerForm();
        $form->addRadioList('rating', 'Hodnotenie', [1 => '1 – Veľmi nespokojný', 2 => '2', 3 => '3', 4 => '4', 5 => '5 – Veľmi spokojný'])->setRequired();
        $form->addTextArea('text', 'Vaša skúsenosť')->setRequired()->addRule($form::MAX_LENGTH, 'Najviac 3 000 znakov.', 3000);
        $form->addSubmit('submit', 'Odoslať recenziu');
        $form->onSuccess[] = function (BaseForm $form): void {
            if ($this->job === null) { $form->addError('Realizácia nie je dostupná.'); return; }
            $v = $form->getValues();
            try { $this->reviews->submit($this->job->getId(), $this->currentCustomer(), $this->accessHash($this->demand->getReference()), (int) $v->rating, $v->text); }
            catch (\InvalidArgumentException $e) { $form->addError('Recenziu možno odoslať až po oznámení vykonaných alebo čiastočne vykonaných prác.'); return; }
            $this->flashSuccess('Recenzia bola uložená.'); $this->redirect('this');
        }; return $form;
    }
	private function accessHash(string $reference): ?string
	{
		$value = $this->session->getSection('demandAccess')[$reference];
		return is_string($value) ? $value : null;
	}
}
