<?php

declare(strict_types=1);

namespace Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250104080341 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE city (id INT AUTO_INCREMENT NOT NULL, district_id INT DEFAULT NULL, region_id INT DEFAULT NULL, fullname VARCHAR(255) NOT NULL, shortname VARCHAR(255) NOT NULL, zip VARCHAR(6) NOT NULL, `use` TINYINT(1) DEFAULT 1 NOT NULL, INDEX IDX_2D5B0234B08FA272 (district_id), INDEX IDX_2D5B023498260155 (region_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE provider (id INT AUTO_INCREMENT NOT NULL, region_id INT DEFAULT NULL, service_category_id INT DEFAULT NULL, city_id INT DEFAULT NULL, state_provider_id INT DEFAULT NULL, created_by INT DEFAULT NULL, company_name VARCHAR(255) NOT NULL, contact_name VARCHAR(64) NOT NULL, contact_surname VARCHAR(64) NOT NULL, contact_title VARCHAR(32) NOT NULL, email VARCHAR(100) NOT NULL, phone_number VARCHAR(100) NOT NULL, ico VARCHAR(100) NOT NULL, dic VARCHAR(100) NOT NULL, password VARCHAR(100) NOT NULL, date_last_login DATETIME DEFAULT CURRENT_TIMESTAMP, note LONGTEXT DEFAULT NULL, street_no VARCHAR(100) NOT NULL, zip_code VARCHAR(100) NOT NULL, date_created DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, date_modified DATETIME DEFAULT CURRENT_TIMESTAMP, date_deleted DATETIME DEFAULT NULL, deleted_by INT DEFAULT NULL, INDEX IDX_92C4739C98260155 (region_id), INDEX IDX_92C4739CDEDCBB4E (service_category_id), INDEX IDX_92C4739C8BAC62AF (city_id), INDEX IDX_92C4739CC1778CF7 (state_provider_id), INDEX IDX_92C4739CDE12AB56 (created_by), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE state_provider (id INT AUTO_INCREMENT NOT NULL, created_by INT DEFAULT NULL, name VARCHAR(32) NOT NULL, date_created DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, date_modified DATETIME DEFAULT CURRENT_TIMESTAMP, date_deleted DATETIME DEFAULT NULL, deleted_by INT DEFAULT NULL, INDEX IDX_AD37153BDE12AB56 (created_by), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE state_user (id INT AUTO_INCREMENT NOT NULL, created_by INT DEFAULT NULL, name VARCHAR(32) NOT NULL, date_created DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, date_modified DATETIME DEFAULT CURRENT_TIMESTAMP, date_deleted DATETIME DEFAULT NULL, deleted_by INT DEFAULT NULL, INDEX IDX_19705F8FDE12AB56 (created_by), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE city ADD CONSTRAINT FK_2D5B0234B08FA272 FOREIGN KEY (district_id) REFERENCES district (id)');
        $this->addSql('ALTER TABLE city ADD CONSTRAINT FK_2D5B023498260155 FOREIGN KEY (region_id) REFERENCES region (id)');
        $this->addSql('ALTER TABLE provider ADD CONSTRAINT FK_92C4739C98260155 FOREIGN KEY (region_id) REFERENCES region (id)');
        $this->addSql('ALTER TABLE provider ADD CONSTRAINT FK_92C4739CDEDCBB4E FOREIGN KEY (service_category_id) REFERENCES category_service (id)');
        $this->addSql('ALTER TABLE provider ADD CONSTRAINT FK_92C4739C8BAC62AF FOREIGN KEY (city_id) REFERENCES city (id)');
        $this->addSql('ALTER TABLE provider ADD CONSTRAINT FK_92C4739CC1778CF7 FOREIGN KEY (state_provider_id) REFERENCES state_provider (id)');
        $this->addSql('ALTER TABLE provider ADD CONSTRAINT FK_92C4739CDE12AB56 FOREIGN KEY (created_by) REFERENCES user (id)');
        $this->addSql('ALTER TABLE state_provider ADD CONSTRAINT FK_AD37153BDE12AB56 FOREIGN KEY (created_by) REFERENCES user (id)');
        $this->addSql('ALTER TABLE state_user ADD CONSTRAINT FK_19705F8FDE12AB56 FOREIGN KEY (created_by) REFERENCES user (id)');
        $this->addSql('ALTER TABLE village DROP FOREIGN KEY village_ibfk_1');
        $this->addSql('DROP TABLE village');
        $this->addSql('ALTER TABLE district CHANGE region_id region_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE district ADD CONSTRAINT FK_31C1548798260155 FOREIGN KEY (region_id) REFERENCES region (id)');
        $this->addSql('CREATE INDEX IDX_31C1548798260155 ON district (region_id)');
        $this->addSql('ALTER TABLE user ADD state_user_id INT DEFAULT NULL, DROP state');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649E10EA933 FOREIGN KEY (state_user_id) REFERENCES state_user (id)');
        $this->addSql('CREATE INDEX IDX_8D93D649E10EA933 ON user (state_user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D649E10EA933');
        $this->addSql('CREATE TABLE village (id INT AUTO_INCREMENT NOT NULL, district_id INT NOT NULL, fullname VARCHAR(255) CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, shortname VARCHAR(255) CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, zip VARCHAR(6) CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, region_id INT NOT NULL, `use` TINYINT(1) DEFAULT 1 NOT NULL, INDEX district_id (district_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb3 COLLATE `utf8mb3_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE village ADD CONSTRAINT village_ibfk_1 FOREIGN KEY (district_id) REFERENCES district (id)');
        $this->addSql('ALTER TABLE city DROP FOREIGN KEY FK_2D5B0234B08FA272');
        $this->addSql('ALTER TABLE city DROP FOREIGN KEY FK_2D5B023498260155');
        $this->addSql('ALTER TABLE provider DROP FOREIGN KEY FK_92C4739C98260155');
        $this->addSql('ALTER TABLE provider DROP FOREIGN KEY FK_92C4739CDEDCBB4E');
        $this->addSql('ALTER TABLE provider DROP FOREIGN KEY FK_92C4739C8BAC62AF');
        $this->addSql('ALTER TABLE provider DROP FOREIGN KEY FK_92C4739CC1778CF7');
        $this->addSql('ALTER TABLE provider DROP FOREIGN KEY FK_92C4739CDE12AB56');
        $this->addSql('ALTER TABLE state_provider DROP FOREIGN KEY FK_AD37153BDE12AB56');
        $this->addSql('ALTER TABLE state_user DROP FOREIGN KEY FK_19705F8FDE12AB56');
        $this->addSql('DROP TABLE city');
        $this->addSql('DROP TABLE provider');
        $this->addSql('DROP TABLE state_provider');
        $this->addSql('DROP TABLE state_user');
        $this->addSql('ALTER TABLE district DROP FOREIGN KEY FK_31C1548798260155');
        $this->addSql('DROP INDEX IDX_31C1548798260155 ON district');
        $this->addSql('ALTER TABLE district CHANGE region_id region_id INT NOT NULL');
        $this->addSql('DROP INDEX IDX_8D93D649E10EA933 ON user');
        $this->addSql('ALTER TABLE user ADD state INT NOT NULL, DROP state_user_id');
    }
}
