<?php declare(strict_types=1);

namespace App\Domain\CategoryService;

use App\Domain\User\User;
use App\Model\Database\EntityManagerDecorator;
use App\Model\Security\Identity;
use Nette\Security\User as NetteUser;
use Nette\DI\Attributes\Inject;

readonly class CategoryServiceFacade
{

	public function __construct(
		private EntityManagerDecorator $em,
		private NetteUser              $user,
	)
	{
	}

	public function getParentCategories(): array
	{
		$categories = $this->em->getRepository(CategoryService::class)->findAll();
		$categoriesArray = [];
		foreach ($categories as $category) {
			$categoriesArray[] = [
				'id' => $category->getId(),
				'name' => $category->getName(),
			];
		}
		return $categoriesArray;
	}

	public function createCategory(array $values): void
	{
		$parent = $values['parentId'] !== null
			? $this->em->getRepository(CategoryService::class)->find($values['parentId'])
			: null;

		/** @var Identity $identity */
		$identity = $this->user->getIdentity();

		/** @var User $userEntity */
		$userEntity = $this->em->getRepository(User::class)->find($identity->getId());

		$category = new CategoryService($values['name'], $values['description'], $parent, $userEntity);
		$this->em->persist($category);
		$this->em->flush();
	}

	public function getCategory(?int $id): ?CategoryService
	{
		return $this->em->getRepository(CategoryService::class)->find($id);
	}

}
