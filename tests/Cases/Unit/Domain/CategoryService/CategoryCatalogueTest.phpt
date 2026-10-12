<?php declare(strict_types=1);

use App\Domain\CategoryService\CategoryCatalogue;
use App\Domain\CategoryService\CategoryService;
use Tester\Assert;

require __DIR__ . '/../../../../../vendor/autoload.php';
Tester\Environment::setup();

/** Doctrine assigns identifiers; unit tests assign them explicitly. */
$category = static function (int $id, string $name, ?CategoryService $parent = null, ?string $description = null): CategoryService {
	$entity = new CategoryService($name, $description, $parent);
	(new ReflectionProperty(CategoryService::class, 'id'))->setValue($entity, $id);

	return $entity;
};
$retire = static function (CategoryService $entity): CategoryService {
	$entity->setDateDeleted(new DateTime('2026-01-01'));

	return $entity;
};

$interior = $category(4, 'Interiér');
$exterior = $category(5, 'Exteriér', null, 'Fasády a strechy');
$garden = $category(6, 'Záhrada');
$other = $category(7, 'Ostatné');
$painting = $category(28, 'Maľovanie', $interior);
$floors = $category(25, 'Podlahy', $interior);
$roof = $category(42, 'Strecha', $exterior);
$retiredService = $retire($category(9, 'Čistenie', $interior));
$retiredRoot = $retire($category(8, 'Zrušená kategória'));
$orphan = $category(80, 'Služba zrušenej kategórie', $retiredRoot);
$grandchild = $category(90, 'Interiérové detaily', $painting);

// Main categories keep creation order (by id) even if the input is ordered by name.
// Services keep the input order, and only selectable rows are offered.
$groups = CategoryCatalogue::groups([$other, $roof, $painting, $retiredService, $exterior, $floors, $orphan, $retiredRoot, $interior, $grandchild, $garden]);
Assert::same(['Interiér', 'Exteriér', 'Záhrada', 'Ostatné'], array_column($groups, 'name'));
Assert::same([4, 5, 6, 7], array_column($groups, 'id'));

// A retired service, a retired main category and its services never appear.
Assert::same([28 => 'Maľovanie', 25 => 'Podlahy', 90 => 'Interiérové detaily'], $groups[0]['categories']);
Assert::false($groups[0]['standalone']);
Assert::same([42 => 'Strecha'], $groups[1]['categories']);
Assert::same('Fasády a strechy', $groups[1]['description']);
Assert::notContains('Služba zrušenej kategórie', array_merge(...array_column($groups, 'categories')));

// A main category without active services stays selectable itself.
Assert::true($groups[2]['standalone']);
Assert::same([6 => 'Záhrada'], $groups[2]['categories']);
Assert::true($groups[3]['standalone']);

// Select options: grouped services under the main category name, standalone ones as plain options.
Assert::same([
	'Interiér' => [28 => 'Maľovanie', 25 => 'Podlahy', 90 => 'Interiérové detaily'],
	'Exteriér' => [42 => 'Strecha'],
	6 => 'Záhrada',
	7 => 'Ostatné',
], CategoryCatalogue::selectOptions($groups));

Assert::same([], CategoryCatalogue::groups([]));
Assert::same([], CategoryCatalogue::groups([$retiredRoot, $orphan]));
