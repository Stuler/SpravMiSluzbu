<?php declare(strict_types=1);
namespace App\UI\Modules\Admin;

use App\Domain\Admin\AdminAccountFacade;
use App\Domain\Customer\CustomerToken;
use App\Domain\Provider\Provider;
use App\Domain\User\User;
use App\UI\Form\BaseForm;
use Nette\DI\Attributes\Inject;

abstract class AccountAdminPresenter extends BaseAdminPresenter
{
    #[Inject] public AdminAccountFacade $accounts;
    protected Provider|User $account;
    protected array $filters = [];
    protected function createComponentStateForm(): BaseForm
    {
        $form = $this->adminForm();
        $form->addCheckbox('confirm', 'Potvrdzujem zmenu stavu účtu.')->setRequired();
        $form->addSubmit('submit', $this->account->isBlocked() ? 'Odblokovať účet' : 'Zablokovať účet');
        $form->onSuccess[] = function (): void {
            $this->accounts->setBlocked($this->account, !$this->account->isBlocked());
            $this->flashSuccess('Stav účtu bol zmenený. Overenie e-mailu zostáva zachované.'); $this->redirect('this');
        };
        return $form;
    }
    protected function createComponentLinkForm(): BaseForm
    {
        $form = $this->adminForm();
        $options = $this->account->isActivated() && $this->account instanceof User
            ? [CustomerToken::RESET => 'Obnova hesla'] : [CustomerToken::ACTIVATE => 'Aktivácia účtu'];
        $form->addSelect('purpose', 'Odkaz', $options)->setRequired(); $form->addSubmit('submit', 'Vyžiadať odoslanie odkazu');
        $form->onSuccess[] = function (BaseForm $form): void {
            try { $this->accounts->sendLink($this->account, $form->getValues()->purpose); }
            catch (\InvalidArgumentException $e) { $form->addError('Pre tento stav účtu nemožno odoslať zvolený odkaz.'); return; }
            catch (\Throwable $e) { $form->addError('Odoslanie sa nepodarilo potvrdiť. Pred opakovaním overte doručenie.'); return; }
            $this->flashSuccess('Odoslanie bolo vyžiadané. Pri aktivácii poskytovateľa platí päťminútový odstup medzi pokusmi.'); $this->redirect('this');
        };
        return $form;
    }
    protected function accountFilterForm(): BaseForm
    {
        $form = new BaseForm(); $form->setMethod('get');
        $form->addText('q', 'Vyhľadávanie')->addRule($form::MAX_LENGTH, 'Najviac 255 znakov.', 255);
        $form->addSelect('state', 'Stav účtu', ['' => 'Všetky', 'active' => 'Aktívny', 'fresh' => 'Neoverený', 'blocked' => 'Blokovaný']); return $form;
    }
    protected function finishFilterForm(BaseForm $form): BaseForm
    {
        $form->addSubmit('filter', 'Filtrovať'); $form->setDefaults($this->filters);
        $form->onSuccess[] = function (BaseForm $form): void {
            $filters = (array) $form->getValues(); unset($filters['filter']); $this->redirect('default', $filters + ['page' => 1]);
        };
        return $form;
    }
    protected function accountHistory(string $type, int $auditPage): void
    {
        $entries = $this->adminAudit->history($type, $this->account->getId(), $auditPage);
        $this->template->auditHasNext = count($entries) > 20; $this->template->auditEntries = array_slice($entries, 0, 20);
        $this->template->auditPage = max(1, $auditPage); $this->template->account = $this->account;
    }
}
