<?php

declare(strict_types=1);

namespace Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240921092008 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE category_service (id INT AUTO_INCREMENT NOT NULL, parent_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, date_created DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, date_modified DATETIME DEFAULT CURRENT_TIMESTAMP, date_deleted DATETIME DEFAULT NULL, created_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, INDEX IDX_2645DAAC727ACA70 (parent_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE category_service ADD CONSTRAINT FK_2645DAAC727ACA70 FOREIGN KEY (parent_id) REFERENCES category_service (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE category_service DROP FOREIGN KEY FK_2645DAAC727ACA70');
        $this->addSql('DROP TABLE category_service');
    }
}
