<?php declare(strict_types=1);
namespace Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261007140000 extends AbstractMigration
{
    public function getDescription(): string { return 'Separate administrative blocking from verification, revoke provider sessions and audit administrative changes.'; }
    public function isTransactional(): bool { return false; }
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE provider ADD administratively_blocked TINYINT(1) DEFAULT 0 NOT NULL, ADD session_version INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE `user` ADD administratively_blocked TINYINT(1) DEFAULT 0 NOT NULL');
        // Legacy blocked accounts retain their block; only an activation timestamp proves verification.
        $this->addSql('UPDATE provider SET administratively_blocked = 1, state_provider_id = CASE WHEN date_activated IS NULL THEN 1 ELSE 2 END WHERE state_provider_id = 3');
        $this->addSql('UPDATE `user` SET administratively_blocked = 1, state_user_id = CASE WHEN date_activated IS NULL THEN 1 ELSE 2 END WHERE state_user_id = 3');
        $this->addSql("CREATE TABLE admin_audit (
            id INT AUTO_INCREMENT NOT NULL, actor_id INT NOT NULL, target_type VARCHAR(32) NOT NULL, target_id INT NOT NULL,
            operation VARCHAR(64) NOT NULL, changes LONGTEXT NOT NULL COMMENT '(DC2Type:json)',
            created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            INDEX IDX_ADMIN_AUDIT_ACTOR (actor_id), INDEX IDX_ADMIN_AUDIT_TARGET (target_type, target_id, created_at), PRIMARY KEY(id),
            CONSTRAINT FK_ADMIN_AUDIT_ACTOR FOREIGN KEY (actor_id) REFERENCES `user` (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
    }
    public function down(Schema $schema): void
    {
        $this->abortIf(true, 'Administrative audit and independent verification states must be preserved. Restore from an explicitly approved backup instead.');
    }
}
