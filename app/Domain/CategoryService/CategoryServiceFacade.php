<?php declare(strict_types=1);
namespace App\Domain\CategoryService;

use App\Domain\Admin\AdminAuditFacade;
use App\Model\Database\EntityManagerDecorator;
use Doctrine\DBAL\LockMode;
use Nette\Utils\ArrayHash;

readonly class CategoryServiceFacade
{
    public function __construct(private EntityManagerDecorator $em, private AdminAuditFacade $audit) {}

    public function getParentCategories(): array
    {
        $result = [];
        foreach ($this->em->getRepository(CategoryService::class)->findBy([], ['name' => 'ASC']) as $category) {
            if ($category->isSelectable()) { $result[$category->getId()] = $category->getName(); }
        }
        return $result;
    }
    public function saveCategory(ArrayHash $values): void
    {
        $actor = $this->audit->requireAdmin();
        $name = trim((string) $values['name']);
        if ($name === '' || mb_strlen($name) > 255) { throw new \InvalidArgumentException('Invalid category name.'); }
        $this->change(function () use ($actor, $name, $values): void {
            $this->lockCatalogue();
            $category = !empty($values['id']) ? $this->getCategory((int) $values['id']) : new CategoryService($name, createdBy: $actor);
            if ($category === null) { throw new \InvalidArgumentException('Unknown category.'); }
            $parentId = (int) ($values['parent_id'] ?? 0);
            $parent = $parentId ? $this->getCategory($parentId) : null;
            if ($parentId && ($parent === null || !$parent->isSelectable())) { throw new \InvalidArgumentException('Invalid category parent.'); }
            $category->setParent($parent);
            $category->setName($name); $category->setDescription(trim((string) ($values['description'] ?? '')) ?: null);
            $category->setDateModified(new \DateTime());
            $this->em->persist($category); $this->em->flush();
            $this->audit->record('category', $category->getId(), 'category_saved', ['parent_id' => $category->getParent()?->getId()]);
        });
    }
    public function getCategory(?int $id): ?CategoryService { return $id ? $this->em->find(CategoryService::class, $id) : null; }
    public function deleteCategory(int $id): void
    {
        $actor = $this->audit->requireAdmin();
        $category = $this->getCategory($id);
        if ($category === null) { throw new \InvalidArgumentException('Unknown category.'); }
        $this->change(function () use ($category, $actor): void {
            $all = $this->lockCatalogue();
            $retired = [];
            foreach ($all as $candidate) {
                $seen = [];
                for ($ancestor = $candidate; $ancestor !== null; $ancestor = $ancestor->getParent()) {
                    if (isset($seen[$ancestor->getId()])) { break; }
                    $seen[$ancestor->getId()] = true;
                    if ($ancestor->getId() === $category->getId()) {
                        if ($candidate->getDateDeleted() === null) {
                            $candidate->setDateDeleted(new \DateTime()); $candidate->setDeletedBy($actor); $retired[] = $candidate->getId();
                        }
                        break;
                    }
                }
            }
            $this->audit->record('category', $category->getId(), 'category_retired', ['retired_ids' => $retired]);
        });
    }
    /** Serialize hierarchy changes so two simultaneous valid moves cannot create a cycle. */
    private function lockCatalogue(): array
    {
        $categories = $this->em->createQueryBuilder()->select('c')->from(CategoryService::class, 'c')->orderBy('c.id', 'ASC')
            ->getQuery()->setLockMode(LockMode::PESSIMISTIC_WRITE)->getResult();
        foreach ($categories as $category) { $this->em->refresh($category); }
        return $categories;
    }
    private function change(callable $operation): void
    {
        $connection = $this->em->getConnection(); $connection->beginTransaction();
        try { $operation(); $this->em->flush(); $connection->commit(); }
        catch (\Throwable $e) { $connection->rollBack(); throw $e; }
    }
    public function getMainCategories(): array { return $this->em->getRepository(CategoryService::class)->findBy(['parent' => null], ['name' => 'ASC']); }
    public function getCategoriesByMainCategoryId(int $categoryId): array { return $this->em->getRepository(CategoryService::class)->findBy(['parent' => $categoryId, 'dateDeleted' => null]); }
    public function updateCategory(int $id, string $name): void
    {
        $category = $this->getCategory($id);
        if ($category === null) { throw new \InvalidArgumentException('Unknown category.'); }
        $this->saveCategory(ArrayHash::from(['id' => $id, 'name' => $name, 'description' => $category->getDescription(), 'parent_id' => $category->getParent()?->getId()]));
    }
    public function getGroupedChildCategoriesForSelect(): array
    {
        $grouped = [];
        foreach ($this->em->getRepository(CategoryService::class)->findBy([], ['name' => 'ASC']) as $category) {
            if ($category->getParent() === null || !$category->isSelectable()) { continue; }
            $grouped[$category->getParent()->getName()][] = ['value' => $category->getId(), 'label' => $category->getName()];
        }
        $result = [];
        foreach ($grouped as $label => $options) { $result[] = ['label' => $label, 'options' => $options]; }
        return $result;
    }
}
