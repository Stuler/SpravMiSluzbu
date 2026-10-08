<?php declare(strict_types=1);
namespace App\UI\Modules\Admin\CategoryService;

use App\Domain\CategoryService\CategoryServiceFacade;
use App\UI\Form\BaseForm;
use App\UI\Modules\Admin\BaseAdminPresenter;
use Nette\DI\Attributes\Inject;

final class CategoryServicePresenter extends BaseAdminPresenter
{
    #[Inject] public CategoryServiceFacade $categoryServiceFacade;
    private ?int $categoryId = null;
    public function renderDefault(): void
    {
        $this->template->title = 'Kategórie služieb';
        $this->template->categories = $this->adminRead->categories();
    }
    public function actionEdit(?int $id = null): void
    {
        if ($id !== null && $this->categoryServiceFacade->getCategory($id) === null) { $this->error('Kategória nebola nájdená.', 404); }
        $this->categoryId = $id;
        $this->template->title = $id ? 'Úprava kategórie služieb' : 'Nová kategória služieb';
        $this->template->category = $id ? $this->categoryServiceFacade->getCategory($id) : null;
    }
    protected function createComponentFormCategoryService(): BaseForm
    {
        $form = $this->adminForm();
        $form->addText('name', 'Názov')->setRequired()->addRule($form::MAX_LENGTH, 'Najviac 255 znakov.', 255);
        $options = $this->categoryServiceFacade->getParentCategories();
        unset($options[$this->categoryId]);
        $category = $this->categoryId ? $this->categoryServiceFacade->getCategory($this->categoryId) : null;
        // Include a retired current parent for display; choosing it is rejected by the domain validation.
        if ($category?->getParent()) { $options[$category->getParent()->getId()] ??= $category->getParent()->getName() . ' (vyradená)'; }
        $form->addSelect('parent_id', 'Nadradená kategória', $options)->setPrompt('Koreňová kategória');
        $form->addTextArea('description', 'Popis');
        $form->addSubmit('submit', 'Uložiť kategóriu');
        if ($category) { $form->setDefaults(['name' => $category->getName(), 'description' => $category->getDescription(), 'parent_id' => $category->getParent()?->getId()]); }
        $form->onSuccess[] = function (BaseForm $form): void {
            $values = $form->getValues(); $values['id'] = $this->categoryId;
            try { $this->categoryServiceFacade->saveCategory($values); }
            catch (\InvalidArgumentException $e) { $form->addError('Skontrolujte názov a nadradenú kategóriu. Kategória nesmie byť pod sebou, svojím potomkom ani vyradenou kategóriou.'); return; }
            $this->flashSuccess('Kategória bola uložená.'); $this->redirect('default');
        };
        return $form;
    }
    protected function createComponentRetireForm(): BaseForm
    {
        $form = $this->adminForm();
        $form->addCheckbox('confirm', 'Vyradiť kategóriu aj všetkých jej potomkov z nového výberu. História zostane zachovaná.')->setRequired();
        $form->addSubmit('submit', 'Vyradiť kategóriu');
        $form->onSuccess[] = function (): void {
            if ($this->categoryId === null) { $this->error('Kategória nebola nájdená.', 404); }
            $this->categoryServiceFacade->deleteCategory($this->categoryId);
            $this->flashSuccess('Kategória a jej potomkovia boli vyradení.'); $this->redirect('default');
        };
        return $form;
    }
}
