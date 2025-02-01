<?php

declare(strict_types=1);

namespace Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250126125232 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE forgotten_password DROP FOREIGN KEY FK_2EDC8D24A76ED395');
        $this->addSql('ALTER TABLE log_access DROP FOREIGN KEY FK_139378F6A76ED395');
        $this->addSql('ALTER TABLE login_permanent DROP FOREIGN KEY FK_BC03AADDA76ED395');
        $this->addSql('DROP TABLE forgotten_password');
        $this->addSql('DROP TABLE log_access');
        $this->addSql('DROP TABLE login_permanent');
        $this->addSql('ALTER TABLE category_service CHANGE created_by created_by INT DEFAULT NULL');
        $this->addSql('ALTER TABLE category_service ADD CONSTRAINT FK_2645DAACDE12AB56 FOREIGN KEY (created_by) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_2645DAACDE12AB56 ON category_service (created_by)');
        $this->addSql('ALTER TABLE provider CHANGE created_by created_by INT DEFAULT NULL');
        $this->addSql('ALTER TABLE provider ADD CONSTRAINT FK_92C4739CDE12AB56 FOREIGN KEY (created_by) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_92C4739CDE12AB56 ON provider (created_by)');
        $this->addSql('ALTER TABLE provider_region CHANGE created_by created_by INT DEFAULT NULL');
        $this->addSql('ALTER TABLE provider_region ADD CONSTRAINT FK_585312E6DE12AB56 FOREIGN KEY (created_by) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_585312E6DE12AB56 ON provider_region (created_by)');
        $this->addSql('ALTER TABLE provider_service_category CHANGE created_by created_by INT DEFAULT NULL');
        $this->addSql('ALTER TABLE provider_service_category ADD CONSTRAINT FK_923FF3E1DE12AB56 FOREIGN KEY (created_by) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_923FF3E1DE12AB56 ON provider_service_category (created_by)');
        $this->addSql('ALTER TABLE state_provider CHANGE created_by created_by INT DEFAULT NULL');
        $this->addSql('ALTER TABLE state_provider ADD CONSTRAINT FK_AD37153BDE12AB56 FOREIGN KEY (created_by) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_AD37153BDE12AB56 ON state_provider (created_by)');
        $this->addSql('ALTER TABLE state_user CHANGE created_by created_by INT DEFAULT NULL');
        $this->addSql('ALTER TABLE state_user ADD CONSTRAINT FK_19705F8FDE12AB56 FOREIGN KEY (created_by) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_19705F8FDE12AB56 ON state_user (created_by)');
        $this->addSql('ALTER TABLE user CHANGE created_by created_by INT DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649DE12AB56 FOREIGN KEY (created_by) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_8D93D649DE12AB56 ON user (created_by)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE forgotten_password (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, uid VARCHAR(12) CHARACTER SET utf8mb3 DEFAULT \'\' NOT NULL COLLATE `utf8mb3_unicode_ci`, ip_address VARCHAR(20) CHARACTER SET utf8mb3 DEFAULT \'\' NOT NULL COLLATE `utf8mb3_unicode_ci`, date_created DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, INDEX IDX_2EDC8D24A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb3 COLLATE `utf8mb3_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE log_access (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, ip_address VARCHAR(32) CHARACTER SET utf8mb3 DEFAULT \'\' NOT NULL COLLATE `utf8mb3_unicode_ci`, date_created DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, INDEX IDX_139378F6A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb3 COLLATE `utf8mb3_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE login_permanent (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, token VARCHAR(64) CHARACTER SET utf8mb3 DEFAULT \'\' NOT NULL COLLATE `utf8mb3_unicode_ci`, last_login DATETIME NOT NULL, date_created DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, INDEX IDX_BC03AADDA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb3 COLLATE `utf8mb3_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE forgotten_password ADD CONSTRAINT FK_2EDC8D24A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE log_access ADD CONSTRAINT FK_139378F6A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE login_permanent ADD CONSTRAINT FK_BC03AADDA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE category_service DROP FOREIGN KEY FK_2645DAACDE12AB56');
        $this->addSql('DROP INDEX IDX_2645DAACDE12AB56 ON category_service');
        $this->addSql('ALTER TABLE category_service CHANGE created_by created_by VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE provider DROP FOREIGN KEY FK_92C4739CDE12AB56');
        $this->addSql('DROP INDEX IDX_92C4739CDE12AB56 ON provider');
        $this->addSql('ALTER TABLE provider CHANGE created_by created_by VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE provider_region DROP FOREIGN KEY FK_585312E6DE12AB56');
        $this->addSql('DROP INDEX IDX_585312E6DE12AB56 ON provider_region');
        $this->addSql('ALTER TABLE provider_region CHANGE created_by created_by VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE provider_service_category DROP FOREIGN KEY FK_923FF3E1DE12AB56');
        $this->addSql('DROP INDEX IDX_923FF3E1DE12AB56 ON provider_service_category');
        $this->addSql('ALTER TABLE provider_service_category CHANGE created_by created_by VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE state_provider DROP FOREIGN KEY FK_AD37153BDE12AB56');
        $this->addSql('DROP INDEX IDX_AD37153BDE12AB56 ON state_provider');
        $this->addSql('ALTER TABLE state_provider CHANGE created_by created_by VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE state_user DROP FOREIGN KEY FK_19705F8FDE12AB56');
        $this->addSql('DROP INDEX IDX_19705F8FDE12AB56 ON state_user');
        $this->addSql('ALTER TABLE state_user CHANGE created_by created_by VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D649DE12AB56');
        $this->addSql('DROP INDEX IDX_8D93D649DE12AB56 ON user');
        $this->addSql('ALTER TABLE user CHANGE created_by created_by VARCHAR(255) DEFAULT NULL');
    }
}
