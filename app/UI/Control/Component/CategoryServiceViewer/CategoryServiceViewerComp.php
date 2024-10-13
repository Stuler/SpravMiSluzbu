<?php declare(strict_types=1);

namespace App\UI\Control\Component\CategoryServiceViewer;

use App\Domain\CategoryService\CategoryServiceFacade;
use App\UI\Control\BaseControl;
use Ublaboo\DataGrid\Column\Action\Confirmation\StringConfirmation;
use Ublaboo\DataGrid\DataGrid;

class CategoryServiceViewerComp extends BaseControl
{

	private ?int $categoryId = null;

	public function __construct(
		private readonly CategoryServiceFacade $categoryServiceFacade,
	)
	{
	}

	public function render(): void
	{

		$this->template->setFile(__DIR__ . '/categoryServiceViewerComp.latte');
		$this->template->render();
	}

	public function createComponentGridCategoryService(): DataGrid
	{
		$grid = new DataGrid();

		if ($this->categoryId === null) {
			return $grid;
		}

		$grid->setDataSource($this->categoryServiceFacade->getCategoriesByMainCategoryId($this->categoryId));
		$grid->addColumnText('name', 'Názov')
			->setEditableCallback([$this, 'columnNameEdited']);

		$grid->addAction('edit', '', 'edit!', ['id'])
			->setClass('btn btn-primary ajax')
			->setIcon('pencil');

		$grid->addAction('delete', '', 'delete!', ['id'])
			->setClass('btn btn-danger ajax')
			->setIcon('trash')
			->setConfirmation(
				new StringConfirmation('Naozaj si prajete odstrániť kategóriu %s?', 'name')
			);

		$grid->addInlineEdit()
			->onControlAdd[] = function ($container): void {
			$container->addText('id', '')->setAttribute('readonly');
			$container->addText('name', 'Názov');
		};


		$grid->addInlineAdd()
			->setClass('btn btn-primary ajax')
			->onControlAdd[] = function ($container) use ($grid) {
			$container->addText('id', '')->setAttribute('readonly');
			$container->addHidden('parent_id', $this->categoryId)->setAttribute('readonly');
			$container->addText('name', 'Názov');
		};

		$grid->getInlineAdd()->onSubmit[] = function ($values) use ($grid) {
			$this->categoryServiceFacade->saveCategory($values);
			$grid->setDataSource($this->categoryServiceFacade->getCategoriesByMainCategoryId($this->categoryId));
			$this->redrawControl();
		};

		return $grid;
	}

	public function columnNameEdited()
	{
		$this->categoryServiceFacade->updateCategory($values);
		$this->flashMessage('Názov kategórie bol úspešne zmenený', 'success');
		$this->redrawControl();
	}

	public function handleEdit($id): void
	{
		$this->presenter->redirect('CategoryService:edit', ['id' => $id]);
	}

	public function handleDelete(int $id): void
	{
		$this->categoryServiceFacade->deleteCategory($id);
		$this->flashMessage('Kategória bola úspešne vymazaná', 'success');
		$this->redirect('this');
	}

	public function setCategoryId(?int $id): void
	{
		$this->categoryId = $id;
	}
}
