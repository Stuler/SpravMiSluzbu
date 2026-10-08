<?php declare(strict_types=1);

namespace Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261007130000 extends AbstractMigration
{
	public function getDescription(): string { return 'Demand processing leases, provider availability, AI audit and provider invitations.'; }
	public function isTransactional(): bool { return false; }
	public function up(Schema $schema): void
	{
		$this->addSql('ALTER TABLE provider ADD accepts_demands TINYINT(1) DEFAULT 1 NOT NULL, ADD service_description LONGTEXT DEFAULT NULL');
		$this->addSql("ALTER TABLE demand ADD processing_attempts INT DEFAULT 0 NOT NULL,
			ADD process_after DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
			ADD lease_until DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', ADD lease_token VARCHAR(32) DEFAULT NULL,
			ADD processing_note VARCHAR(255) DEFAULT NULL, ADD service_summary LONGTEXT DEFAULT NULL");
		$this->addSql('CREATE INDEX IDX_DEMAND_QUEUE ON demand (status, process_after, lease_until)');
		$this->addSql("CREATE TABLE provider_invitation (
			id INT AUTO_INCREMENT NOT NULL, demand_id INT NOT NULL, provider_id INT NOT NULL,
			reason VARCHAR(500) NOT NULL, response VARCHAR(16) NOT NULL, delivery VARCHAR(20) NOT NULL,
			created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
			responded_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
			delivery_attempted_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
			UNIQUE INDEX UNIQ_INVITATION_DEMAND_PROVIDER (demand_id, provider_id), INDEX IDX_INVITATION_PROVIDER (provider_id),
			INDEX IDX_INVITATION_DELIVERY (delivery), PRIMARY KEY(id),
			CONSTRAINT FK_INVITATION_DEMAND FOREIGN KEY (demand_id) REFERENCES demand (id),
			CONSTRAINT FK_INVITATION_PROVIDER FOREIGN KEY (provider_id) REFERENCES provider (id)
		) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
		$this->addSql("CREATE TABLE ai_run (
			id INT AUTO_INCREMENT NOT NULL, demand_id INT NOT NULL, prompt_version VARCHAR(80) NOT NULL, model VARCHAR(100) NOT NULL,
			input LONGTEXT NOT NULL COMMENT '(DC2Type:json)', output LONGTEXT NOT NULL COMMENT '(DC2Type:json)',
			input_tokens INT NOT NULL, output_tokens INT NOT NULL, duration_ms INT NOT NULL,
			created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_AI_RUN_DEMAND (demand_id), PRIMARY KEY(id),
			CONSTRAINT FK_AI_RUN_DEMAND FOREIGN KEY (demand_id) REFERENCES demand (id)
		) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
	}
	public function down(Schema $schema): void
	{
		$this->abortIf((int) $this->connection->fetchOne('SELECT COUNT(*) FROM provider_invitation') > 0,
			'Export and explicitly remove invitations before rolling back.');
		$this->addSql('DROP TABLE ai_run');
		$this->addSql('DROP TABLE provider_invitation');
		$this->addSql('DROP INDEX IDX_DEMAND_QUEUE ON demand');
		$this->addSql('ALTER TABLE demand DROP processing_attempts, DROP process_after, DROP lease_until, DROP lease_token, DROP processing_note, DROP service_summary');
		$this->addSql('ALTER TABLE provider DROP accepts_demands, DROP service_description');
	}
}
