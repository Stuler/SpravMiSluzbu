<?php declare(strict_types=1);

namespace Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261012130000 extends AbstractMigration
{
	/** Names of throw-away catalogue rows left behind by manual testing. */
	private const TEST_NAMES = ['Test', 'Test 1', 'Test 2', 'Testovanie 2'];

	public function getDescription(): string
	{
		return 'Retire leftover test service categories (soft delete, history and provider links are preserved).';
	}

	public function up(Schema $schema): void
	{
		$placeholders = implode(', ', array_fill(0, count(self::TEST_NAMES), '?'));

		// Like retirement in administration: a retired category takes its direct children with it.
		// The derived table lets MySQL read the rows it updates.
		$this->addSql(
			"UPDATE category_service SET date_deleted = NOW()
			WHERE date_deleted IS NULL
				AND parent_id IN (SELECT id FROM (SELECT id FROM category_service WHERE name IN ($placeholders)) AS test_parent)",
			self::TEST_NAMES
		);
		$this->addSql(
			"UPDATE category_service SET date_deleted = NOW() WHERE date_deleted IS NULL AND name IN ($placeholders)",
			self::TEST_NAMES
		);
	}

	public function down(Schema $schema): void
	{
		$this->abortIf(true, 'The migration cannot tell retired test rows from other retired rows. Restore a category in administration or from a backup instead.');
	}
}
