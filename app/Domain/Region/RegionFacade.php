<?php declare(strict_types=1);

namespace App\Domain\Region;

use App\Domain\CategoryService\CategoryService;
use App\Domain\User\User;
use App\Domain\User\UserService;
use App\Model\Database\EntityManagerDecorator;
use App\Model\Security\Identity;
use Nette\DI\Attributes\Inject;
use Nette\Security\User as NetteUser;
use Nette\Utils\ArrayHash;

readonly class RegionFacade
{

	public function __construct(
		private EntityManagerDecorator $em,
	)
	{
	}

	/**
	 * Get all regions formatted for select input
	 */
	public function getRegionsForSelect(): array
	{
		$regions = $this->em->getRepository(Region::class)->findAll();
		return array_map(fn($region) => [
			'id' => $region->getId(),
			'name' => $region->getName(),
		], $regions);
	}

}
