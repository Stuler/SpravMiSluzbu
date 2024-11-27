<?php declare(strict_types = 1);

namespace App\UI\Modules\Admin\Customer;

use App\Domain\Order\Event\OrderCreated;
use App\UI\Modules\Admin\BaseAdminPresenter;
use Nette\Application\UI\Form;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class CustomerPresenter extends BaseAdminPresenter
{

	public function renderDefault()
	{

	}

}
