<?php declare(strict_types=1);

namespace App\UI\Modules\Admin\Demand;

use App\Domain\Demand\Demand;
use App\Domain\Demand\DemandFacade;
use App\Domain\Matching\MatchingFacade;
use App\Domain\Matching\InvitationFacade;
use App\UI\Form\BaseForm;
use App\UI\Form\FormFactory;
use App\UI\Modules\Admin\BaseAdminPresenter;
use Nette\DI\Attributes\Inject;

final class DemandPresenter extends BaseAdminPresenter
{
	#[Inject] public DemandFacade $demands;
	#[Inject] public MatchingFacade $matching;
	#[Inject] public InvitationFacade $invitations;
	#[Inject] public FormFactory $formFactory;
	#[Inject] public \App\Domain\Admin\AdminDemandFacade $adminDemands;
	private Demand $demand;
	private array $filters = [];
	protected function startup(): void
	{
		parent::startup();
		$this->session->start();
		$this->getHttpResponse()->setHeader('Cache-Control', 'no-store');
		$this->getHttpResponse()->setHeader('Referrer-Policy', 'no-referrer');
	}
	public function actionDefault(string $q = '', string $status = '', ?int $region = null, string $locality = '', string $from = '', string $to = '', string $issue = '', int $page = 1): void
	{
		$this->filters = compact('q', 'status', 'region', 'locality', 'from', 'to', 'issue');
		$this->template->filters = $this->filters;
		try { $this->template->list = $this->adminRead->demands($this->filters, $page); }
		catch (\InvalidArgumentException $e) { $this->error('Neplatný dátum filtra. Použite formát RRRR-MM-DD.', 400); }
	}
	protected function createComponentFilterForm(): BaseForm
	{
		$form = new BaseForm(); $form->setMethod('get');
		$form->addText('q', 'Vyhľadávanie')->addRule($form::MAX_LENGTH, 'Najviac 255 znakov.', 255);
		$form->addSelect('status', 'Stav', ['' => 'Všetky', Demand::PENDING => 'Čaká na overenie', Demand::READY => 'Čaká na spracovanie', Demand::REVIEW => 'Čaká na kontrolu', Demand::NO_PROVIDERS => 'Bez poskytovateľov', Demand::INVITED => 'Firmy oslovené', Demand::CLOSED => 'Uzavretý']);
		$form->addSelect('region', 'Región', $this->adminRead->regionOptions())->setPrompt('Všetky regióny');
		$form->addText('locality', 'Obec');
		$form->addText('from', 'Od dátumu')->setHtmlType('date'); $form->addText('to', 'Do dátumu')->setHtmlType('date');
		$form->addSelect('issue', 'Prevádzkový problém', ['' => 'Všetky', 'review' => 'Kontrola zadania', 'no_providers' => 'Bez poskytovateľov', 'mail' => 'Nepotvrdené e-maily', 'responses' => 'Čaká na reakcie']);
		$form->addSubmit('filter', 'Filtrovať'); $form->setDefaults($this->filters);
		$form->onSuccess[] = function (BaseForm $form): void {
			$values = (array) $form->getValues(); unset($values['filter']); $this->redirect('default', $values + ['page' => 1]);
		};
		return $form;
	}
	public function actionDetail(string $reference): void
	{
		$demand = $this->demands->findByReference($reference);
		if ($demand === null) { $this->error('Dopyt nebol nájdený.', 404); }
		$this->demand = $demand;
		$this->template->preview = null;
	}
	public function renderDetail(): void
	{
		$this->template->demand = $this->demand;
		$this->template->invitations = $this->matching->invitations($this->demand);
		$this->template->runs = $this->matching->runs($this->demand);
	}
	private function form(): BaseForm
	{
		$form = $this->formFactory->forBackend();
		$form->setTranslator(new \App\UI\Form\SlovakFormTranslator());
		$form->addProtection('Platnosť formulára vypršala. Obnovte stránku.');
		return $form;
	}
	protected function createComponentRequeueForm(): BaseForm
	{
		$form = $this->form();
		$categories = $this->demands->categoryOptions();
		$current = $this->demand->getCategory()?->getId();
		$form->addSelect('category', 'Kategória', $categories)->setPrompt('Nezaradené')->setDefaultValue(isset($categories[$current]) ? $current : null);
		$form->addSubmit('submit', 'Uložiť kategóriu a zaradiť na spracovanie');
		$form->onSuccess[] = function (BaseForm $form): void {
			try { $this->adminDemands->requeue($this->demand, $form->getValues()->category ? (int) $form->getValues()->category : null); }
			catch (\InvalidArgumentException $e) { $form->addError('Možno spracovať iba otvorený a overený dopyt.'); return; }
			$this->flashSuccess('Dopyt čaká na spracovanie. Existujúce oslovenia sa neodošlú opakovane.');
			$this->redirect('detail', ['reference' => $this->demand->getReference()]);
		};
		return $form;
	}
	protected function createComponentPreviewForm(): BaseForm
	{
		$form = $this->form();
		$form->addSubmit('submit', 'Vyskúšať AI výber bez oslovenia firiem');
		$form->onSuccess[] = function (BaseForm $form): void {
			try { $this->template->preview = $this->matching->preview($this->demand); }
			catch (\Throwable $e) { $form->addError('Skúšobný výber sa nepodaril. Overte nastavenie a dostupnosť AI.'); }
		};
		return $form;
	}
	protected function createComponentApproveForm(): BaseForm
	{
		$form = $this->form();
		$options = [];
		foreach ($this->matching->eligibleProviders($this->demand, $this->demand->getCategory() ? [$this->demand->getCategory()->getId()] : []) as $provider) {
			$options[$provider->getId()] = $provider->getCompanyName();
		}
		$form->addMultiSelect('providers', 'Poskytovatelia (najviac traja)', $options)->setRequired();
		$form->addSubmit('submit', 'Potvrdiť výber a pripraviť oslovenia');
		$form->onSuccess[] = function (BaseForm $form): void {
			try { $this->adminDemands->approve($this->demand, $form->getValues()->providers); }
			catch (\InvalidArgumentException $e) { $form->addError('Vyberte najviac tri vhodné firmy pre otvorený, overený a zaradený dopyt.'); return; }
			$this->flashSuccess('Výber bol uložený. Oslovenia čakajú na odoslanie.');
			$this->redirect('detail', ['reference' => $this->demand->getReference()]);
		};
		return $form;
	}
	protected function createComponentRetryMailForm(): BaseForm
	{
		$form = $this->form();
		$options = [];
		foreach ($this->matching->invitations($this->demand) as $i) {
			if (in_array($i->getDelivery(), ['unknown', 'sending'], true)) { $options[$i->getId()] = $i->getProvider()->getCompanyName(); }
		}
		$form->addSelect('invitation', 'Nepotvrdené odoslanie', $options)->setPrompt('Vyberte firmu')->setRequired();
		$form->addCheckbox('confirm', 'Overil som doručenie a beriem na vedomie možnosť duplicitného e-mailu.')->setRequired();
		$form->addSubmit('submit', 'Zaradiť e-mail na opätovné odoslanie');
		$form->onSuccess[] = function (BaseForm $form): void {
			try { $this->adminDemands->retry($this->demand, (int) $form->getValues()->invitation); }
			catch (\InvalidArgumentException $e) { $form->addError('Opakovanie je možné pri otvorenom dopyte aspoň päť minút po pokuse o odoslanie.'); return; }
			$this->redirect('detail', ['reference' => $this->demand->getReference()]);
		};
		return $form;
	}
}
