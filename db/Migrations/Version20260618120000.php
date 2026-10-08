<?php

declare(strict_types=1);

namespace Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Provider activation tokens and unique email.
 *
 * Normalizes existing provider emails, enforces a unique provider email index and
 * replaces the legacy predictable `hash` column with secure single-use activation
 * token fields (SHA-256 hash + expiry + sent timestamp). Activation status and
 * date_activated are preserved.
 */
final class Version20260618120000 extends AbstractMigration
{
	public function getDescription(): string
	{
		return 'Normalize provider emails, add unique email index and replace legacy hash with activation token fields.';
	}

	public function up(Schema $schema): void
	{
		// Abort early if normalizing emails would create duplicates, before adding the unique index.
		$duplicates = (int) $this->connection->fetchOne(
			'SELECT COUNT(*) FROM (
				SELECT LOWER(TRIM(email)) AS normalized_email
				FROM provider
				GROUP BY LOWER(TRIM(email))
				HAVING COUNT(*) > 1
			) AS duplicates'
		);
		$this->abortIf(
			$duplicates > 0,
			'Cannot add a unique provider email index: normalizing existing emails produces duplicates. '
			. 'Resolve the duplicate provider emails manually before running this migration.'
		);

		// Normalize stored emails (trim + lowercase).
		$this->addSql('UPDATE provider SET email = LOWER(TRIM(email))');

		// Activation token fields. New columns default to NULL, clearing any legacy token state.
		$this->addSql('ALTER TABLE provider
			ADD activation_token_hash CHAR(64) DEFAULT NULL,
			ADD activation_token_expires_at DATETIME DEFAULT NULL,
			ADD activation_sent_at DATETIME DEFAULT NULL');

		// Drop the legacy predictable hash column.
		$this->addSql('ALTER TABLE provider DROP hash');

		// Enforce unique normalized email within the provider table.
		$this->addSql('CREATE UNIQUE INDEX UNIQ_PROVIDER_EMAIL ON provider (email)');
	}

	public function down(Schema $schema): void
	{
		$this->addSql('DROP INDEX UNIQ_PROVIDER_EMAIL ON provider');

		// Re-add the legacy column. Original hash values cannot be restored, so it is
		// recreated with an empty default to keep the rollback safe on populated tables.
		$this->addSql("ALTER TABLE provider ADD hash VARCHAR(255) NOT NULL DEFAULT ''");
		$this->addSql('ALTER TABLE provider
			DROP activation_token_hash,
			DROP activation_token_expires_at,
			DROP activation_sent_at');
	}
}
