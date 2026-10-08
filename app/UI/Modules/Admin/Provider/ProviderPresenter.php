<?php declare(strict_types=1);
namespace App\UI\Modules\Admin\Provider;

use App\Domain\Demand\DemandFacade;
use App\Domain\Provider\ProviderFacade;
use App\UI\Form\BaseForm;
use App\UI\Modules\Admin\AccountAdminPresenter;
use Nette\DI\Attributes\Inject;

final class ProviderPresenter extends AccountAdminPresenter
{
    #[Inject] public ProviderFacade $providers;
    #[Inject] public DemandFacade $demands;
    public function actionDefault(string $q = '', string $state = '', string $accepts = '', ?int $category = null, ?int $region = null, int $page = 1): void
    {
        $this->filters = compact('q', 'state', 'accepts', 'category', 'region');
        $this->template->filters = $this->filters;
        $this->template->list = $this->adminRead->providers($this->filters, $page);
    }
    public function actionDetail(int $id, int $page = 1, int $auditPage = 1): void
    {
        $provider = $this->accounts->provider($id);
        if ($provider === null) { $this->error('Poskytovateľ nebol nájdený.', 404); }
        $this->account = $provider;
        $this->template->provider = $provider;
        $this->template->regions = $this->providers->getProviderRegions($provider);
        $this->template->categories = $this->providers->getProviderServiceCategories($provider);
        $this->template->list = $this->adminRead->providerInvitations($provider, $page);
        $this->accountHistory('provider', $auditPage);
    }
    protected function createComponentFilterForm(): BaseForm
    {
        $form = $this->accountFilterForm();
        $form->addSelect('accepts', 'Prijímanie dopytov', ['' => 'Všetky', '1' => 'Prijíma', '0' => 'Pozastavené']);
        $form->addSelect('category', 'Kategória', $this->demands->categoryOptions())->setPrompt('Všetky kategórie');
        $form->addSelect('region', 'Región', $this->adminRead->regionOptions())->setPrompt('Všetky regióny');
        return $this->finishFilterForm($form);
    }
    protected function createComponentProfileForm(): BaseForm
    {
        $p = $this->account; $form = $this->adminForm();
        $fields = ['companyName' => ['Firma', 255, $p->getCompanyName()], 'contactName' => ['Meno kontaktu', 64, $p->getContactName()],
            'surname' => ['Priezvisko kontaktu', 64, $p->getSurname()], 'title' => ['Titul', 32, $p->getContactTitle()],
            'phone' => ['Telefón', 100, $p->getPhoneNumber()], 'ico' => ['IČO', 100, $p->getIco()], 'dic' => ['DIČ', 100, $p->getDic()],
            'streetNo' => ['Ulica a číslo', 100, $p->getStreetNo()], 'zipCode' => ['PSČ', 100, $p->getZipCode()]];
        foreach ($fields as $key => [$label, $limit, $value]) {
            $input = $form->addText($key, $label)->addRule($form::MAX_LENGTH, 'Príliš dlhá hodnota.', $limit)->setDefaultValue($value);
            if (in_array($key, ['companyName', 'contactName', 'surname', 'phone', 'streetNo', 'zipCode'], true)) { $input->setRequired(); }
        }
        $cityOptions = $this->demands->cityOptions();
        $cityOptions[$p->getCity()->getId()] ??= $p->getCity()->getFullname();
        $form->addSelect('city', 'Obec', $cityOptions)->setRequired()->setDefaultValue($p->getCity()->getId());
        $form->addTextArea('description', 'Opis služieb')->addRule($form::MAX_LENGTH, 'Najviac 2 000 znakov.', 2000)->setDefaultValue($p->getServiceDescription());
        $form->addCheckbox('accepts', 'Prijíma nové dopyty')->setDefaultValue($p->acceptsDemands());
        $categoryIds = array_map(fn($c): int => $c->getId(), $this->providers->getProviderServiceCategories($p));
        $categoryOptions = $this->demands->categoryOptions();
        $form->addMultiSelect('categories', 'Aktívne služby', $categoryOptions)->setRequired()->setDefaultValue(array_values(array_intersect($categoryIds, array_keys($categoryOptions))));
        $regionIds = array_map(fn($r): int => $r->getId(), $this->providers->getProviderRegions($p));
        $regionOptions = $this->adminRead->regionOptions();
        $form->addMultiSelect('regions', 'Regióny', $regionOptions)->setRequired()->setDefaultValue(array_values(array_intersect($regionIds, array_keys($regionOptions))));
        $form->addSubmit('submit', 'Uložiť profil a pôsobnosť');
        $form->onSuccess[] = function (BaseForm $form): void {
            try { $this->accounts->updateProvider($this->account, (array) $form->getValues()); }
            catch (\InvalidArgumentException $e) { $form->addError('Skontrolujte profil, telefón, aktívne služby a regióny.'); return; }
            $this->flashSuccess('Profil bol uložený. Pôsobnosť sa použije pri ďalšom výbere.'); $this->redirect('this');
        };
        return $form;
    }
}
