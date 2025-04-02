<?php declare(strict_types=1);

namespace App\Domain\City;

use App\Domain\CategoryService\CategoryService;
use App\Domain\Region\Region;
use App\Domain\User\User;
use App\Domain\User\UserService;
use App\Model\Database\EntityManagerDecorator;
use App\Model\Security\Identity;
use Nette\DI\Attributes\Inject;
use Nette\Security\User as NetteUser;
use Nette\Utils\ArrayHash;

readonly class CityFacade
{

	public function __construct(
		private EntityManagerDecorator $em,
	)
	{
	}

	/**
	 * Get all cities formatted for select input
	 */
	public function getCitiesForSelect(): array
	{
		$cities = $this->em->getRepository(City::class)->findAll();
		return array_map(fn($city) => [
			'id' => $city->getId(),
			'name' => $city->getFullName(),
			'region_id' => $city->getRegion()->getId(),
			'zip' => $city->getZip(),
		], $cities);
	}

}
