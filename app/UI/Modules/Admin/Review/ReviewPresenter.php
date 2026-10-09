<?php declare(strict_types=1);
namespace App\UI\Modules\Admin\Review;
use App\UI\Modules\Admin\BaseAdminPresenter;
use App\UI\Form\BaseForm;
use Nette\DI\Attributes\Inject;
final class ReviewPresenter extends BaseAdminPresenter
{
    #[Inject] public \App\Domain\Review\ReviewFacade $reviews;
    private \App\Domain\Review\Review $review;
    public function actionDefault(int $page = 1): void
    {
        $this->template->reviews = $this->reviews->pending($page);
        $this->template->jobs = $this->reviews->disputed(); $this->template->page = max(1, $page);
        $reviewByJob = []; foreach ($this->template->jobs as $job) { $reviewByJob[$job->getId()] = $this->reviews->forJob($job); }
        $this->template->reviewByJob = $reviewByJob;
    }
    public function actionDetail(int $id): void
    {
        $review = $this->reviews->findForAdmin($id);
        if ($review === null) { $this->error('Recenzia nebola nájdená.', 404); }
        $this->review = $review; $this->template->review = $review;
    }
    protected function createComponentDecisionForm(): BaseForm
    {
        $form = $this->adminForm();
        $form->addRadioList('publish', 'Rozhodnutie', [1 => 'Spolupráca overená – zverejniť', 0 => 'Zamietnuť'])->setRequired();
        $form->addTextArea('reason', 'Dôvod rozhodnutia')->setRequired()->addRule($form::MAX_LENGTH, 'Najviac 1 000 znakov.', 1000);
        $form->addSubmit('submit', 'Uložiť rozhodnutie');
        $form->onSuccess[] = function (BaseForm $form): void {
            $v = $form->getValues();
            try { $this->reviews->decide($this->review->getId(), (bool) $v->publish, $v->reason); }
            catch (\InvalidArgumentException $e) { $form->addError('Rozhodnutie už nie je dostupné.'); return; }
            $this->redirect('this');
        }; return $form;
    }
    protected function createComponentResolveJobForm(): \Nette\Application\UI\Multiplier
    {
        return new \Nette\Application\UI\Multiplier(function (string $id): BaseForm {
            $form = $this->adminForm();
            $form->addRadioList('verified', 'Spolupráca', [1 => 'Overená', 0 => 'Nepotvrdená – zrušiť dohodu'])->setRequired();
            $form->addTextArea('reason', 'Dôvod rozhodnutia')->setRequired()->addRule($form::MAX_LENGTH, 'Najviac 1 000 znakov.', 1000);
            $form->addSubmit('submit', 'Uložiť rozhodnutie');
            $form->onSuccess[] = function (BaseForm $form) use ($id): void {
                $v = $form->getValues();
                try { $this->reviews->resolveJob((int) $id, (bool) $v->verified, $v->reason); }
                catch (\InvalidArgumentException $e) { $form->addError('Rozhodnite v detaile recenzie alebo obnovte stránku.'); return; }
                $this->redirect('this');
            }; return $form;
        });
    }
}
