<?php declare(strict_types = 1);

namespace App\UI\Modules\Admin;

use App\Model\App;
use App\Model\Database\EntityManagerDecorator;
use App\UI\Modules\Base\SecuredPresenter;
use Nette\DI\Attributes\Inject;

abstract class BaseAdminPresenter extends SecuredPresenter
{

	#[Inject]
	public EntityManagerDecorator $em;

	public function checkRequirements(mixed $element): void
	{
		parent::checkRequirements($element);

		if (!$this->user->isAllowed('Admin:Home')) {
			$this->flashError('You cannot access this with user role');
			$this->redirect(App::DESTINATION_FRONT_HOMEPAGE);
		}
	}

}
