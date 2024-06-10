<?php

declare(strict_types=1);

namespace Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240601121738 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE forgotten_password (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, uid VARCHAR(12) DEFAULT \'\' NOT NULL, ip_address VARCHAR(20) DEFAULT \'\' NOT NULL, date_created DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, INDEX IDX_2EDC8D24A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE log_access (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, ip_address VARCHAR(32) DEFAULT \'\' NOT NULL, date_created DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, INDEX IDX_139378F6A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE login_permanent (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, token VARCHAR(64) DEFAULT \'\' NOT NULL, last_login DATETIME NOT NULL, date_created DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, INDEX IDX_BC03AADDA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE login_role (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(64) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, login_role_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, surname VARCHAR(255) NOT NULL, username VARCHAR(100) NOT NULL, email VARCHAR(100) NOT NULL, password VARCHAR(100) DEFAULT NULL, date_last_login DATETIME DEFAULT CURRENT_TIMESTAMP, note LONGTEXT DEFAULT NULL, date_created DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, date_modified DATETIME DEFAULT CURRENT_TIMESTAMP, date_deleted DATETIME DEFAULT NULL, created_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, INDEX IDX_8D93D649C62E3CD1 (login_role_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE forgotten_password ADD CONSTRAINT FK_2EDC8D24A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE log_access ADD CONSTRAINT FK_139378F6A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE login_permanent ADD CONSTRAINT FK_BC03AADDA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649C62E3CD1 FOREIGN KEY (login_role_id) REFERENCES login_role (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE forgotten_password DROP FOREIGN KEY FK_2EDC8D24A76ED395');
        $this->addSql('ALTER TABLE log_access DROP FOREIGN KEY FK_139378F6A76ED395');
        $this->addSql('ALTER TABLE login_permanent DROP FOREIGN KEY FK_BC03AADDA76ED395');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D649C62E3CD1');
        $this->addSql('DROP TABLE forgotten_password');
        $this->addSql('DROP TABLE log_access');
        $this->addSql('DROP TABLE login_permanent');
        $this->addSql('DROP TABLE login_role');
        $this->addSql('DROP TABLE user');
    }
}
