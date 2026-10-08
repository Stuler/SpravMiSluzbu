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
	#[Inject]
	public \App\Domain\Admin\AdminAuditFacade $adminAudit;
	#[Inject]
	public \App\Domain\Admin\AdminReadFacade $adminRead;

	protected function startup(): void
	{
		parent::startup();
		$this->adminAudit->requireAdmin();
		$this->session->start();
		$this->getHttpResponse()->setHeader('Cache-Control', 'no-store');
		$this->getHttpResponse()->setHeader('Referrer-Policy', 'no-referrer');
	}

	protected function adminForm(): \App\UI\Form\BaseForm
	{
		$form = new \App\UI\Form\BaseForm();
		$form->setTranslator(new \App\UI\Form\SlovakFormTranslator());
		$form->addProtection('Platnosť formulára vypršala. Obnovte stránku.');
		return $form;
	}

	public function checkRequirements(mixed $element): void
	{
		parent::checkRequirements($element);

		if (!$this->user->isAllowed('Admin:Home')) {
			$this->flashError('Nemáte oprávnenie na prístup do administrácie.');
			$this->redirect(App::DESTINATION_FRONT_HOMEPAGE);
		}
	}

}
