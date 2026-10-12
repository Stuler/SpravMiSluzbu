<?php declare(strict_types = 1);

namespace App\UI\Modules\Front\Home;

use App\Domain\Demand\DemandFacade;
use App\UI\Modules\Front\BaseFrontPresenter;
use Nette\DI\Attributes\Inject;

final class HomePresenter extends BaseFrontPresenter
{

	#[Inject]
	public DemandFacade $demands;

	public function renderDefault(): void
	{
		$this->template->categoryGroups = $this->demands->categoryGroups();
	}

}
