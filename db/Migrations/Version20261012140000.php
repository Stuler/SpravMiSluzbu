<?php declare(strict_types=1);

namespace Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261012140000 extends AbstractMigration
{
	/**
	 * UTF-8 text that was imported through a wrong client charset becomes double encoded:
	 * "Interiér" turns into "InteriÃ©r", "Čistenie" into "ÄŒistenie". The characters Ã, Â, Å and Ä
	 * (UTF-8 bytes C3 83, C3 82, C3 85, C3 84) start such sequences. Slovak text only uses Ä as a
	 * rare capital, which no catalogue name begins with. The check works on bytes, because a
	 * case/accent-insensitive collation would treat Ã and Ä as plain "a".
	 */
	private const DOUBLE_ENCODED = "(LOCATE(UNHEX('C383'), CAST(name AS BINARY)) > 0 OR LOCATE(UNHEX('C382'), CAST(name AS BINARY)) > 0
		OR LOCATE(UNHEX('C385'), CAST(name AS BINARY)) > 0 OR LOCATE(UNHEX('C384'), CAST(name AS BINARY)) > 0)";

	public function getDescription(): string
	{
		return 'Retire service categories with double-encoded names (soft delete, history and provider links are preserved).';
	}

	public function up(Schema $schema): void
	{
		// Like retirement in administration: a retired category takes its direct children with it.
		// The derived table lets MySQL read the rows it updates.
		$this->addSql(
			'UPDATE category_service SET date_deleted = NOW()
			WHERE date_deleted IS NULL
				AND parent_id IN (SELECT id FROM (SELECT id FROM category_service WHERE ' . self::DOUBLE_ENCODED . ') AS broken_parent)'
		);
		$this->addSql('UPDATE category_service SET date_deleted = NOW() WHERE date_deleted IS NULL AND ' . self::DOUBLE_ENCODED);
	}

	public function down(Schema $schema): void
	{
		$this->abortIf(true, 'The migration cannot tell these retired rows from other retired rows. Restore a category in administration or from a backup instead.');
	}
}
