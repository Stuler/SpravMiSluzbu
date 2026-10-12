<?php declare(strict_types=1);

namespace Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261012120000 extends AbstractMigration
{
	/**
	 * Main categories in display order, each with its services: name => description (or null).
	 * Retired rows (date_deleted set) are never revived. A missing active row is inserted instead,
	 * so existing provider and demand links keep pointing at the rows they were created with.
	 */
	private const CATALOGUE = [
		'Interiér' => [
			'Oprava zariadení' => null,
			'Čistenie' => null,
			'Stavebné práce a prerábanie' => null,
			'Elektroinštalácia' => null,
			'Podlahy' => null,
			'Teplo, ventilácia a klimatizácia' => null,
			'Upratovanie' => null,
			'Maľovanie' => null,
			'Kúrenie a voda' => null,
			'Výroba a skladanie nábytku' => null,
			'Okná a dvere' => null,
			'Kúpeľňa' => null,
			'Kuchyňa' => null,
			'Odpad' => null,
		],
		'Exteriér' => [
			'Brány a dvere' => null,
			'Príjazdové cesty, chodníky' => 'Vrátane betónových dlažieb.',
			'Garážové brány' => null,
			'Omietky, fasády' => null,
			'Odkvap (čistenie, oprava, inštalácia)' => null,
			'Stavebné práce' => null,
			'Strecha' => null,
			'Obklady' => null,
			'Zateplenie' => null,
			'Komíny' => null,
		],
		'Záhrada' => [
			'Terasy' => null,
			'Oplotenie' => null,
			'Zameranie pozemku' => null,
			'Záhradná architektúra' => null,
			'Trávnik a záhradné práce' => null,
			'Pestovanie' => null,
			'Zavlažovanie' => null,
			'Záhradné domy a prístrešky' => null,
			'Bazény' => null,
			'Oddychové zóny' => null,
			'Stromy' => null,
			'Stojany, záhradné lavičky, koše' => null,
			'Štrky, piesky, okrasné kamene' => null,
			'Záhradný nábytok' => null,
		],
		'Ostatné' => [
			'Výstavba domov' => null,
			'Vlhkosť a izolácia' => null,
			'Zámočníctvo' => null,
			'Sťahovanie' => null,
			'Ochrana pred škodcami' => null,
			'Zabezpečovacie systémy' => null,
			'Železiarske práce' => null,
			'Práca s drevom' => null,
			'Osvetlenie' => null,
			'Inteligentná domácnosť' => null,
			'Tvorivá práca' => null,
			'Domáci miláčikovia' => null,
			'IT a Design' => null,
			'Deti' => null,
		],
	];

	public function getDescription(): string
	{
		return 'Seed the service catalogue: main categories Interiér, Exteriér, Záhrada, Ostatné and their services (idempotent).';
	}

	public function up(Schema $schema): void
	{
		foreach (self::CATALOGUE as $main => $services) {
			// Roots are inserted in catalogue order so that their ids keep the display order.
			$this->addSql(
				'INSERT INTO category_service (parent_id, name, description, date_created)
				SELECT NULL, ?, NULL, NOW() FROM DUAL
				WHERE NOT EXISTS (SELECT 1 FROM category_service WHERE parent_id IS NULL AND name = ? AND date_deleted IS NULL)',
				[$main, $main]
			);
			foreach ($services as $name => $description) {
				$this->addSql(
					'INSERT INTO category_service (parent_id, name, description, date_created)
					SELECT p.id, ?, ?, NOW() FROM category_service p
					WHERE p.parent_id IS NULL AND p.name = ? AND p.date_deleted IS NULL
						AND NOT EXISTS (SELECT 1 FROM category_service c WHERE c.parent_id = p.id AND c.name = ? AND c.date_deleted IS NULL)
					ORDER BY p.id LIMIT 1',
					[$name, $description, $main, $name]
				);
			}
		}
	}

	public function down(Schema $schema): void
	{
		$this->abortIf(true, 'Categories may already be linked to providers and demands. Retire them in administration instead of rolling back.');
	}
}
