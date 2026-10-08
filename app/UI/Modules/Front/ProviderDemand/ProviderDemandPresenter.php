<?php declare(strict_types=1);

namespace App\UI\Modules\Front\ProviderDemand;

use App\Domain\Matching\InvitationFacade;
use App\Domain\Matching\ProviderInvitation;
use App\Domain\Provider\Provider;
use App\UI\Form\BaseForm;
use App\UI\Modules\Front\BaseFrontPresenter;
use Nette\DI\Attributes\Inject;

final class ProviderDemandPresenter extends BaseFrontPresenter
{
	#[Inject]
	public InvitationFacade $invitations;
	private Provider $provider;
	private ?ProviderInvitation $invitation = null;
	protected function startup(): void
	{
		parent::startup();
		$this->session->start();
		$this->getHttpResponse()->setHeader('Cache-Control', 'no-store');
		$this->getHttpResponse()->setHeader('Referrer-Policy', 'no-referrer');
		$this->getHttpResponse()->setHeader('X-Robots-Tag', 'noindex, nofollow');
		$provider = $this->user->isProvider() ? $this->invitations->activeProvider((int) $this->user->getId(), (int) ($this->user->getIdentity()->getData()['providerSessionVersion'] ?? 1)) : null;
		if ($provider === null) { $this->user->logout(true); $this->redirect(':Front:ProviderSign:in'); }
		$this->provider = $provider;
	}
	public function actionDefault(): void
	{
		$this->template->invitations = $this->invitations->forProvider($this->provider);
	}
	public function actionDetail(int $id): void
	{
		$this->invitation = $this->invitations->findForProvider($id, $this->provider);
		if ($this->invitation === null) { $this->error('Dopyt nie je dostupný.', 404); }
		$this->template->invitation = $this->invitation;
	}
	private function form(): BaseForm
	{
		$form = $this->baseFormFactory->forFrontend();
		$form->setTranslator(new \App\UI\Form\SlovakFormTranslator());
		$form->addProtection('Platnosť formulára vypršala. Obnovte stránku.');
		return $form;
	}
	protected function createComponentProfileForm(): BaseForm
	{
		$form = $this->form();
		$form->addCheckbox('accepts', 'Prijímam nové dopyty')->setDefaultValue($this->provider->acceptsDemands());
		$form->addTextArea('description', 'Aké služby poskytujete a ktoré práce neprijímate?')->setDefaultValue($this->provider->getServiceDescription())
			->setHtmlAttribute('rows', 5)->addRule($form::MAX_LENGTH, 'Opis môže mať najviac 2 000 znakov.', 2000);
		$form->addSubmit('submit', 'Uložiť nastavenia');
		$form->onSuccess[] = function (BaseForm $form): void {
			$v = $form->getValues();
			$this->invitations->updateProfile($this->provider, $v->accepts, $v->description);
			$this->flashSuccess('Nastavenia boli uložené.');
			$this->redirect('default');
		};
		return $form;
	}
	protected function createComponentResponseForm(): BaseForm
	{
		$form = $this->form();
		$form->addRadioList('accept', 'Vaša odpoveď', [1 => 'Mám záujem a chcem kontaktovať zákazníka', 0 => 'Nemám záujem'])->setRequired('Vyberte odpoveď.');
		$form->addSubmit('submit', 'Potvrdiť odpoveď');
		$form->onSuccess[] = function (BaseForm $form): void {
			if ($this->invitation === null) { $this->error('Dopyt nie je dostupný.', 404); }
			try { $this->invitations->respond($this->invitation->getId(), $this->provider, (bool) $form->getValues()->accept); }
			catch (\InvalidArgumentException $e) { $form->addError('Dopyt už nie je dostupný na odpoveď.'); return; }
			$this->flashSuccess('Odpoveď bola uložená.');
			$this->redirect('detail', ['id' => $this->invitation->getId()]);
		};
		return $form;
	}
}
