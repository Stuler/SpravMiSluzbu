<?php declare(strict_types=1);
namespace Database\Migrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261008140000 extends AbstractMigration
{
    public function getDescription(): string { return 'Jobs, independent collaboration verification, one review per job and administrative decisions.'; }
    public function isTransactional(): bool { return false; }
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE demand ADD matching_stopped TINYINT(1) DEFAULT 0 NOT NULL');
        // MySQL permits multiple NULL values in this unique key: cancellations keep history.
        $this->addSql("CREATE TABLE job (
            id INT AUTO_INCREMENT NOT NULL, demand_id INT NOT NULL, provider_id INT NOT NULL, active_demand_id INT DEFAULT NULL,
            status VARCHAR(24) NOT NULL, verification VARCHAR(24) NOT NULL,
            selected_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', confirmed_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            performed_on DATE DEFAULT NULL COMMENT '(DC2Type:date_immutable)', outcome VARCHAR(16) DEFAULT NULL,
            description VARCHAR(1000) DEFAULT NULL, dispute_reason VARCHAR(1000) DEFAULT NULL,
            PRIMARY KEY(id), INDEX IDX_JOB_DEMAND (demand_id), INDEX IDX_JOB_PROVIDER (provider_id), UNIQUE INDEX UNIQ_JOB_ACTIVE (active_demand_id),
            CONSTRAINT FK_JOB_DEMAND FOREIGN KEY (demand_id) REFERENCES demand(id),
            CONSTRAINT FK_JOB_ACTIVE FOREIGN KEY (active_demand_id) REFERENCES demand(id),
            CONSTRAINT FK_JOB_PROVIDER FOREIGN KEY (provider_id) REFERENCES provider(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        $this->addSql("CREATE TABLE review (
            id INT AUTO_INCREMENT NOT NULL, job_id INT NOT NULL, decided_by_id INT DEFAULT NULL,
            rating SMALLINT NOT NULL, text VARCHAR(3000) NOT NULL, status VARCHAR(16) NOT NULL,
            created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', published_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            decided_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', decision_reason VARCHAR(1000) DEFAULT NULL,
            reply VARCHAR(2000) DEFAULT NULL, replied_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            PRIMARY KEY(id), UNIQUE INDEX UNIQ_REVIEW_JOB (job_id), INDEX IDX_REVIEW_ACTOR (decided_by_id),
            CONSTRAINT FK_REVIEW_JOB FOREIGN KEY (job_id) REFERENCES job(id), CONSTRAINT FK_REVIEW_ACTOR FOREIGN KEY (decided_by_id) REFERENCES `user`(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
    }
    public function down(Schema $schema): void { $this->abortIf(true, 'Preserve job and review history. Restore only from an explicitly approved backup.'); }
}
