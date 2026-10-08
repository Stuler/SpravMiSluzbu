<?php declare(strict_types=1);

namespace App\UI\Modules\Front;

use App\Domain\Customer\CustomerAccountFacade;
use App\Domain\User\User;
use App\Model\Security\RateLimit\PublicRateLimiter;
use App\UI\Form\BaseForm;
use Nette\DI\Attributes\Inject;

abstract class CustomerBasePresenter extends BaseFrontPresenter
{
	#[Inject]
	public CustomerAccountFacade $accounts;
	#[Inject]
	public PublicRateLimiter $rateLimiter;

	protected function startup(): void
	{
		parent::startup();
		$this->session->start();
		$this->getHttpResponse()->setHeader('Cache-Control', 'no-store');
		$this->getHttpResponse()->setHeader('Referrer-Policy', 'no-referrer');
		$this->getHttpResponse()->setHeader('X-Robots-Tag', 'noindex, nofollow');
	}

	protected function currentCustomer(): ?User
	{
		if (!$this->user->isCustomer()) { return null; }
		$version = (int) ($this->user->getIdentity()->getData()['customerSessionVersion'] ?? 0);
		$customer = $this->accounts->findActiveCustomer((int) $this->user->getId(), $version);
		if ($customer === null) { $this->user->logout(true); }
		return $customer;
	}

	protected function customerForm(): BaseForm
	{
		$form = $this->baseFormFactory->forFrontend();
		$form->setTranslator(new \App\UI\Form\SlovakFormTranslator());
		$form->addProtection('Platnosť formulára vypršala. Obnovte stránku a skúste to znova.');
		return $form;
	}

	protected function allowRequest(BaseForm $form, string $action, string $email = ''): bool
	{
		$ip = $this->getHttpRequest()->getRemoteAddress() ?? 'unknown';
		$allowed = $this->rateLimiter->consume($action . ':ip:' . $ip, 20, 3600);
		if ($email !== '') {
			$allowed = $this->rateLimiter->consume($action . ':email:' . \App\Model\Utils\Emails::normalize($email), 5, 3600) && $allowed;
		}
		if (!$allowed) { $form->addError('Príliš veľa pokusov. Skúste to neskôr.'); }
		return $allowed;
	}
}
