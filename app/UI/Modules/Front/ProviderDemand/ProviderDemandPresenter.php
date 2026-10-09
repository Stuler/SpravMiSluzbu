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
	#[Inject] public \App\Domain\Job\JobFacade $jobs;
	#[Inject] public \App\Domain\Review\ReviewFacade $reviews;
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
		$this->template->jobs = $this->jobs->forProvider($this->provider);
		$reviewByJob = []; foreach ($this->template->jobs as $job) { $reviewByJob[$job->getId()] = $this->reviews->forJob($job); }
		$this->template->reviewByJob = $reviewByJob;
	}
	public function actionDetail(int $id): void
	{
		$this->invitation = $this->invitations->findForProvider($id, $this->provider);
		if ($this->invitation === null) { $this->error('Dopyt nie je dostupný.', 404); }
		$this->template->invitation = $this->invitation;
	}
	    protected function createComponentJobForm(): \Nette\Application\UI\Multiplier
    {
        return new \Nette\Application\UI\Multiplier(function (string $id): BaseForm {
            $form = $this->form();
            $form->addSelect('action', 'Dohoda a spolupráca', ['confirm' => 'Potvrdiť dohodu', 'decline' => 'Odmietnuť dohodu', 'dispute' => 'Popieram spoluprácu na tejto zákazke'])->setPrompt('Vyberte možnosť')->setRequired();
            $form->addTextArea('reason', 'Dôvod popretia spolupráce')->addRule($form::MAX_LENGTH, 'Najviac 1 000 znakov.', 1000);
            $form->addSubmit('submit', 'Uložiť');
            $form->onSuccess[] = function (BaseForm $form) use ($id): void {
                $v = $form->getValues();
                try { $this->jobs->providerAction((int) $id, $this->provider, $v->action, $v->reason); }
                catch (\InvalidArgumentException $e) { $form->addError('Operácia nie je dostupná. Pri popretí spolupráce uveďte dôvod.'); return; }
                $this->redirect('this');
            }; return $form;
        });
    }
    protected function createComponentReplyForm(): \Nette\Application\UI\Multiplier
    {
        return new \Nette\Application\UI\Multiplier(function (string $id): BaseForm {
            $form = $this->form();
            $form->addTextArea('text', 'Vaša verejná odpoveď')->setRequired()->addRule($form::MAX_LENGTH, 'Najviac 2 000 znakov.', 2000);
            $form->addSubmit('submit', 'Zverejniť odpoveď');
            $form->onSuccess[] = function (BaseForm $form) use ($id): void {
                try { $this->reviews->reply((int) $id, $this->provider, $form->getValues()->text); }
                catch (\InvalidArgumentException $e) { $form->addError('Odpoveď už existuje alebo recenzia nie je zverejnená.'); return; }
                $this->redirect('this');
            }; return $form;
        });
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
