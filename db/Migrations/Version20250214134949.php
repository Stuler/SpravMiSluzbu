<?php

declare(strict_types=1);

namespace Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250214134949 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE category_service CHANGE deleted_by deleted_by INT DEFAULT NULL');
        $this->addSql('ALTER TABLE category_service ADD CONSTRAINT FK_2645DAAC1F6FA0AF FOREIGN KEY (deleted_by) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_2645DAAC1F6FA0AF ON category_service (deleted_by)');
        $this->addSql('ALTER TABLE provider ADD hash VARCHAR(255) NOT NULL, ADD date_activated DATETIME DEFAULT NULL, CHANGE deleted_by deleted_by INT DEFAULT NULL');
        $this->addSql('ALTER TABLE provider ADD CONSTRAINT FK_92C4739C1F6FA0AF FOREIGN KEY (deleted_by) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_92C4739C1F6FA0AF ON provider (deleted_by)');
        $this->addSql('ALTER TABLE provider_region CHANGE deleted_by deleted_by INT DEFAULT NULL');
        $this->addSql('ALTER TABLE provider_region ADD CONSTRAINT FK_585312E61F6FA0AF FOREIGN KEY (deleted_by) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_585312E61F6FA0AF ON provider_region (deleted_by)');
        $this->addSql('ALTER TABLE provider_service_category CHANGE deleted_by deleted_by INT DEFAULT NULL');
        $this->addSql('ALTER TABLE provider_service_category ADD CONSTRAINT FK_923FF3E11F6FA0AF FOREIGN KEY (deleted_by) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_923FF3E11F6FA0AF ON provider_service_category (deleted_by)');
        $this->addSql('ALTER TABLE state_provider CHANGE deleted_by deleted_by INT DEFAULT NULL');
        $this->addSql('ALTER TABLE state_provider ADD CONSTRAINT FK_AD37153B1F6FA0AF FOREIGN KEY (deleted_by) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_AD37153B1F6FA0AF ON state_provider (deleted_by)');
        $this->addSql('ALTER TABLE state_user CHANGE deleted_by deleted_by INT DEFAULT NULL');
        $this->addSql('ALTER TABLE state_user ADD CONSTRAINT FK_19705F8F1F6FA0AF FOREIGN KEY (deleted_by) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_19705F8F1F6FA0AF ON state_user (deleted_by)');
        $this->addSql('ALTER TABLE user CHANGE deleted_by deleted_by INT DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D6491F6FA0AF FOREIGN KEY (deleted_by) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_8D93D6491F6FA0AF ON user (deleted_by)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE category_service DROP FOREIGN KEY FK_2645DAAC1F6FA0AF');
        $this->addSql('DROP INDEX IDX_2645DAAC1F6FA0AF ON category_service');
        $this->addSql('ALTER TABLE category_service CHANGE deleted_by deleted_by VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE provider DROP FOREIGN KEY FK_92C4739C1F6FA0AF');
        $this->addSql('DROP INDEX IDX_92C4739C1F6FA0AF ON provider');
        $this->addSql('ALTER TABLE provider DROP hash, DROP date_activated, CHANGE deleted_by deleted_by VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE provider_region DROP FOREIGN KEY FK_585312E61F6FA0AF');
        $this->addSql('DROP INDEX IDX_585312E61F6FA0AF ON provider_region');
        $this->addSql('ALTER TABLE provider_region CHANGE deleted_by deleted_by VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE provider_service_category DROP FOREIGN KEY FK_923FF3E11F6FA0AF');
        $this->addSql('DROP INDEX IDX_923FF3E11F6FA0AF ON provider_service_category');
        $this->addSql('ALTER TABLE provider_service_category CHANGE deleted_by deleted_by VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE state_provider DROP FOREIGN KEY FK_AD37153B1F6FA0AF');
        $this->addSql('DROP INDEX IDX_AD37153B1F6FA0AF ON state_provider');
        $this->addSql('ALTER TABLE state_provider CHANGE deleted_by deleted_by VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE state_user DROP FOREIGN KEY FK_19705F8F1F6FA0AF');
        $this->addSql('DROP INDEX IDX_19705F8F1F6FA0AF ON state_user');
        $this->addSql('ALTER TABLE state_user CHANGE deleted_by deleted_by VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D6491F6FA0AF');
        $this->addSql('DROP INDEX IDX_8D93D6491F6FA0AF ON user');
        $this->addSql('ALTER TABLE user CHANGE deleted_by deleted_by VARCHAR(255) DEFAULT NULL');
    }
}
