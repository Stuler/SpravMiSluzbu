<?php declare(strict_types=1);

namespace App\Domain\CategoryService;

use App\Domain\User\User;
use App\Domain\User\UserService;
use App\Model\Database\EntityManagerDecorator;
use App\Model\Security\Identity;
use Nette\DI\Attributes\Inject;
use Nette\Security\User as NetteUser;
use Nette\Utils\ArrayHash;

readonly class CategoryServiceFacade
{

	public function __construct(
		private EntityManagerDecorator $em,
		private NetteUser              $user,
		private UserService            $userService,
	)
	{
	}

	/**
	 * Get all categories with parent_id = null
	 */
	public function getParentCategories(): array
	{
		$categories = $this->em->getRepository(CategoryService::class)->findBy(['parent' => null]);
		$categoriesArray = [];
		foreach ($categories as $category) {
			$categoriesArray[$category->getId()] = $category->getName();
		}
		return $categoriesArray;
	}

	public function saveCategory(ArrayHash $values): void
	{
		if ($values['id'] != null) {
			$this->updateCategory((int)$values['id'], (string)$values['name']);
			return;
		}

		$parent = $values['parent_id'] !== null
			? $this->em->getRepository(CategoryService::class)->find($values['parent_id'])
			: null;

		/** @var Identity $identity */
		$identity = $this->user->getIdentity();

		/** @var User $userEntity */
		$userEntity = $this->em->getRepository(User::class)->find($identity->getId());

		$category = new CategoryService(
			name: $values['name'],
			description: $values['description'] ?? null,
			parent: $parent,
			createdBy: $userEntity
		);
		$this->em->persist($category);
		$this->em->flush();
	}

	public function getCategory(?int $id): ?CategoryService
	{
		return $this->em->getRepository(CategoryService::class)->find($id);
	}

	public function deleteCategory(int $id): void
	{
		$category = $this->em->getRepository(CategoryService::class)->find($id);
		$category->setDateDeleted(new \DateTime());
		$userEntity = $this->userService->getUserEntity();
		$category->setDeletedBy($userEntity);
		$this->em->flush();
	}

	/**
	 * Get all categories with parent_id = null
	 * @return array
	 */
	public function getMainCategories(): array
	{
		return $this->em->getRepository(CategoryService::class)->findBy(['parent' => null]);
	}

	/**
	 * Get all categories with parent_id = $categoryId
	 * @param int $categoryId
	 * @return array
	 */
	public function getCategoriesByMainCategoryId(int $categoryId): array
	{
		return $this->em->getRepository(CategoryService::class)
			->findBy([
				'parent' => $categoryId,
				'dateDeleted' => null
			]);
	}

	public function updateCategory(int $id, string $name): void
	{
		$category = $this->em->getRepository(CategoryService::class)->find($id);
		$category->setName($name);
		$category->setDateModified(new \DateTime());
		$this->em->flush();
	}

}
