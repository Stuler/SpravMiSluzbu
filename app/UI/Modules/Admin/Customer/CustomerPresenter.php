<?php declare(strict_types = 1);

namespace App\UI\Modules\Admin\Customer;

use App\UI\Form\BaseForm;
use App\UI\Modules\Admin\AccountAdminPresenter;

final class CustomerPresenter extends AccountAdminPresenter
{

	public function actionDefault(string $q = '', string $state = '', int $page = 1): void
	{
		$this->filters = compact('q', 'state'); $this->template->filters = $this->filters;
		$this->template->list = $this->adminRead->customers($this->filters, $page);
	}
	public function actionDetail(int $id, int $page = 1, int $auditPage = 1): void
	{
		$customer = $this->accounts->customer($id);
		if ($customer === null) { $this->error('Zákazník nebol nájdený.', 404); }
		$this->account = $customer; $this->template->customer = $customer;
		$this->template->list = $this->adminRead->customerDemands($customer, $page);
		$this->accountHistory('customer', $auditPage);
	}
	protected function createComponentFilterForm(): BaseForm { return $this->finishFilterForm($this->accountFilterForm()); }
	protected function createComponentProfileForm(): BaseForm
	{
		$form = $this->adminForm();
		$form->addText('name', 'Meno')->setRequired()->addRule($form::MAX_LENGTH, 'Najviac 255 znakov.', 255)->setDefaultValue($this->account->getName());
		$form->addText('surname', 'Priezvisko')->addRule($form::MAX_LENGTH, 'Najviac 255 znakov.', 255)->setDefaultValue($this->account->getSurname());
		$form->addSubmit('submit', 'Uložiť meno');
		$form->onSuccess[] = function (BaseForm $form): void {
			try { $this->accounts->updateCustomer($this->account, $form->getValues()->name, $form->getValues()->surname); }
			catch (\InvalidArgumentException $e) { $form->addError('Skontrolujte meno zákazníka.'); return; }
			$this->flashSuccess('Meno bolo uložené. Kontakty existujúcich dopytov zostali zachované.'); $this->redirect('this');
		};
		return $form;
	}

}
