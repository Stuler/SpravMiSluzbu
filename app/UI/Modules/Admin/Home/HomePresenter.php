<?php declare(strict_types=1);
namespace App\UI\Modules\Admin\Home;

use App\UI\Modules\Admin\BaseAdminPresenter;

final class HomePresenter extends BaseAdminPresenter
{
    public function renderDefault(): void { $this->template->overview = $this->adminRead->overview(); }
}
