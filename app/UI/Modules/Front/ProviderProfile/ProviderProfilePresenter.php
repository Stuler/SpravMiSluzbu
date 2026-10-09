<?php declare(strict_types=1);
namespace App\UI\Modules\Front\ProviderProfile;
use App\UI\Modules\Front\BaseFrontPresenter;
use Nette\DI\Attributes\Inject;
final class ProviderProfilePresenter extends BaseFrontPresenter
{
    #[Inject] public \App\Domain\Matching\InvitationFacade $providers;
    #[Inject] public \App\Domain\Review\ReviewFacade $reviews;
    public function actionDetail(int $id, int $page = 1): void
    {
        $provider = $this->providers->activeProvider($id);
        if ($provider === null) { $this->error('Profil nie je dostupný.', 404); }
        $this->template->provider = $provider;
        $this->template->reviews = $this->reviews->published($provider, $page);
        $this->template->summary = $this->reviews->summary($provider);
        $this->template->page = max(1, $page);
    }
}
