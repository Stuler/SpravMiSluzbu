<?php declare(strict_types=1);
namespace App\UI\Modules\Admin\Dashboard;

use App\UI\Modules\Admin\BaseAdminPresenter;

final class DashboardPresenter extends BaseAdminPresenter
{
    public function actionDefault(): void { $this->redirect(':Admin:Home:default'); }
}
