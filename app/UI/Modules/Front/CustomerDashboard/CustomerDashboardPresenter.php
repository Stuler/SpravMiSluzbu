<?php declare(strict_types=1);

namespace App\UI\Modules\Front\CustomerDashboard;

use App\Domain\Demand\DemandFacade;
use App\UI\Modules\Front\CustomerBasePresenter;
use Nette\DI\Attributes\Inject;

final class CustomerDashboardPresenter extends CustomerBasePresenter
{
	#[Inject]
	public DemandFacade $demands;

	public function actionDefault(int $page = 1): void
	{
		$customer = $this->currentCustomer();
		if ($customer === null) { $this->redirect(':Front:UserSign:in'); }
		$page = max(1, min(10000, $page));
		$demands = $this->demands->forCustomer($customer, $page);
		$this->template->hasNext = count($demands) > 20;
		$this->template->demands = array_slice($demands, 0, 20);
		$this->template->page = $page;
	}
}
