<?php declare(strict_types=1);
namespace App\UI\Control\Component\CategoryServiceViewer;

use App\Domain\CategoryService\CategoryServiceFacade;
use App\UI\Control\BaseControl;
use Ublaboo\DataGrid\DataGrid;

class CategoryServiceViewerComp extends BaseControl
{
    private ?int $categoryId = null;
    public function __construct(private readonly CategoryServiceFacade $categoryServiceFacade) {}
    public function render(): void
    {
        $this->template->setFile(__DIR__ . '/categoryServiceViewerComp.latte'); $this->template->render();
    }
    public function createComponentGridCategoryService(): DataGrid
    {
        $grid = new DataGrid();
        if ($this->categoryId === null) { return $grid; }
        $grid->setDataSource($this->categoryServiceFacade->getCategoriesByMainCategoryId($this->categoryId));
        $grid->addColumnText('name', 'Názov');
        // All edits and retirement now use the authorized presenter's CSRF-protected POST forms.
        $grid->addAction('edit', 'Upraviť', 'edit', ['id'])->setClass('btn btn-primary')->setIcon('pencil');
        return $grid;
    }
    public function handleEdit(int $id): void { $this->presenter->redirect('CategoryService:edit', ['id' => $id]); }
    public function setCategoryId(?int $id): void { $this->categoryId = $id; }
}
