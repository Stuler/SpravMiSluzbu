<?php declare(strict_types=1);

namespace App\UI\Modules\Front\ProviderDashboard;

use App\Domain\Provider\ProviderFacade;
use App\Model\App;
use App\UI\Modules\Front\BaseFrontPresenter;
use Nette\DI\Attributes\Inject;

/**
 * Secured, read-only onboarding dashboard for authenticated providers.
 */
final class ProviderDashboardPresenter extends BaseFrontPresenter
{

	#[Inject]
	public ProviderFacade $providerFacade;
	#[Inject]
	public \App\Domain\Matching\InvitationFacade $invitations;

	public function checkRequirements(mixed $element): void
	{
		// Access requires an authenticated session, the provider role and a
		// provider-scoped identity (accountType=provider).
		if (!$this->user->isProvider()) {
			$this->flashWarning('Pre prístup do prehľadu poskytovateľa sa prihláste.');
			$this->redirect(App::DESTINATION_PROVIDER_SIGN_IN);
		}
	}

	public function actionDefault(): void
	{
		$provider = $this->providerFacade->getProvider((int) $this->user->getId());
		if ($provider === null || $this->invitations->activeProvider($provider->getId(), (int) ($this->user->getIdentity()->getData()['providerSessionVersion'] ?? 1)) === null) {
			$this->user->logout(true);
			$this->redirect(App::DESTINATION_PROVIDER_SIGN_IN);
		}

		$this->template->provider = $provider;
		$this->template->regions = $this->providerFacade->getProviderRegions($provider);
		$this->template->serviceCategories = $this->providerFacade->getProviderServiceCategories($provider);
	}

}
