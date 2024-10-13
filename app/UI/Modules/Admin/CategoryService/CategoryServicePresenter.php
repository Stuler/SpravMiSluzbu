<?php declare(strict_types=1);

namespace App\UI\Modules\Admin\CategoryService;

use App\Domain\CategoryService\CategoryServiceFacade;
use App\UI\Control\Component\CategoryServiceViewer\CategoryServiceViewerCompFactory;
use App\UI\Form\BaseForm;
use App\UI\Form\FormFactory;
use App\UI\Modules\Admin\BaseAdminPresenter;
use Nette\Application\UI\Multiplier;
use Nette\DI\Attributes\Inject;

final class CategoryServicePresenter extends BaseAdminPresenter
{

	#[Inject]
	public FormFactory $formFactory;

	#[Inject]
	public CategoryServiceFacade $categoryServiceFacade;

	#[Inject]
	public CategoryServiceViewerCompFactory $categoryServiceViewerCompFactory;

	public function renderDefault(?string $type = null): void
	{
		$this->template->type = $type;
		$this->template->title = "Kategórie služieb";
		$this->template->mainCategories = $this->categoryServiceFacade->getMainCategories();
	}

	public function renderEdit(?int $id = null): void
	{
		$this->template->title = "Editácia kategórie služieb";
		if ($id) {
			$category = $this->categoryServiceFacade->getCategory($id);
			$this['formCategoryService']->setDefaults(
				[
					'id' => $category->getId(),
					'name' => $category->getName(),
					'parent_id' => $category->getParent()->getId(),
					'description' => $category->getDescription(),
				]
			);
		}
	}

	public function createComponentFormCategoryService(): BaseForm
	{
		$form = $this->formFactory->forBackend();
		$form->addHidden('id');
		$form->addText('name', 'Názov')
			->setRequired();
		$form->addSelect('parent_id', 'Nadradená kategória', $this->categoryServiceFacade->getParentCategories())
			->setPrompt('Vyberte nadradenú kategóriu');
		$form->addTextArea('description', 'Popis');
		$form->addSubmit('submit');
		$form->onSuccess[] = [$this, 'processCategoryForm'];

		return $form;
	}

	public function processCategoryForm(BaseForm $form): void
	{
		$values = $form->getValues();
		$this->categoryServiceFacade->saveCategory($values);
		$this->flashMessage('Kategória bola úspešne vytvorená', 'success');
		$this->redirect('this');
	}

	public function createComponentCategoryServiceViewer(): Multiplier
	{
		return new Multiplier(function (string $categoryId) {
			$c = $this->categoryServiceViewerCompFactory->create();
			$c->setCategoryId((int)$categoryId);
			return $c;
		});
	}

}
