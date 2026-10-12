<?php declare(strict_types=1);

namespace App\Domain\CategoryService;

/**
 * Read model of the public service catalogue: main categories with the services
 * a customer can pick. Only selectable (non-retired) services are offered, so a
 * demand never lands on a main category that has no provider coverage of its own.
 */
final class CategoryCatalogue
{
	/**
	 * A main category with at least one active child is offered through its children.
	 * A main category without children stays selectable itself ("standalone").
	 *
	 * @param iterable<CategoryService> $categories
	 * @return list<array{id: int, name: string, description: ?string, standalone: bool, categories: array<int, string>}>
	 */
	public static function groups(iterable $categories): array
	{
		$roots = [];
		$children = [];
		foreach ($categories as $category) {
			if (!$category->isSelectable()) {
				continue;
			}
			$root = self::root($category);
			if ($root === $category) {
				$roots[$category->getId()] = $category;
				continue;
			}
			$children[$root->getId()][$category->getId()] = $category->getName();
			$roots[$root->getId()] ??= $root;
		}

		// Main categories keep their creation order; services keep the order of the input.
		ksort($roots);
		$groups = [];
		foreach ($roots as $id => $root) {
			$standalone = !isset($children[$id]);
			$groups[] = [
				'id' => $id,
				'name' => $root->getName(),
				'description' => $root->getDescription(),
				'standalone' => $standalone,
				'categories' => $standalone ? [$id => $root->getName()] : $children[$id],
			];
		}

		return $groups;
	}

	/**
	 * Options for a select box: services grouped under their main category,
	 * standalone main categories as plain options.
	 *
	 * @param list<array{id: int, name: string, description: ?string, standalone: bool, categories: array<int, string>}> $groups
	 * @return array<int|string, string|array<int, string>>
	 */
	public static function selectOptions(array $groups): array
	{
		$options = [];
		foreach ($groups as $group) {
			if ($group['standalone']) {
				$options += $group['categories'];
				continue;
			}
			$options[$group['name']] = $group['categories'];
		}

		return $options;
	}

	private static function root(CategoryService $category): CategoryService
	{
		$seen = [];
		while (($parent = $category->getParent()) !== null && !isset($seen[spl_object_id($category)])) {
			$seen[spl_object_id($category)] = true;
			$category = $parent;
		}

		return $category;
	}
}
