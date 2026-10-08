<?php declare(strict_types=1);

namespace Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261007120000 extends AbstractMigration
{
	public function getDescription(): string
	{
		return 'Customer account tokens, optional customer address, session revocation and private service demands.';
	}

	public function isTransactional(): bool { return false; }

	public function up(Schema $schema): void
	{
		$this->abortIf((int) $this->connection->fetchOne('SELECT COUNT(*) FROM (SELECT LOWER(TRIM(email)) FROM `user` GROUP BY LOWER(TRIM(email)) HAVING COUNT(*) > 1) duplicates') > 0,
			'Normalize and resolve duplicate user emails before applying this migration.');
		$this->addSql('UPDATE `user` SET email = LOWER(TRIM(email))');
		$this->addSql('CREATE UNIQUE INDEX UNIQ_CUSTOMER_EMAIL ON `user` (email)');
		$this->addSql('ALTER TABLE `user` ADD customer_session_version INT DEFAULT 1 NOT NULL, MODIFY street_no VARCHAR(100) DEFAULT NULL, MODIFY city VARCHAR(100) DEFAULT NULL, MODIFY zip_code VARCHAR(100) DEFAULT NULL');
		$this->addSql("CREATE TABLE customer_token (
			id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, purpose VARCHAR(16) NOT NULL,
			token_hash VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
			used_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
			UNIQUE INDEX UNIQ_CUSTOMER_TOKEN (token_hash), INDEX IDX_CUSTOMER_TOKEN_USER (user_id), PRIMARY KEY(id),
			CONSTRAINT FK_CUSTOMER_TOKEN_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE
		) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
		$this->addSql("CREATE TABLE demand (
			id INT AUTO_INCREMENT NOT NULL, customer_id INT DEFAULT NULL, city_id INT NOT NULL, category_id INT DEFAULT NULL,
			reference VARCHAR(32) NOT NULL, submission_key VARCHAR(64) NOT NULL, description LONGTEXT NOT NULL,
			requested_term VARCHAR(255) NOT NULL, contact_name VARCHAR(255) NOT NULL, contact_email VARCHAR(100) NOT NULL,
			contact_phone VARCHAR(32) DEFAULT NULL, status VARCHAR(32) NOT NULL,
			created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
			verified_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
			closed_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
			access_token_hash VARCHAR(64) DEFAULT NULL, access_expires_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
			UNIQUE INDEX UNIQ_DEMAND_REFERENCE (reference), UNIQUE INDEX UNIQ_DEMAND_SUBMISSION (submission_key),
			UNIQUE INDEX UNIQ_DEMAND_ACCESS (access_token_hash), INDEX IDX_DEMAND_CUSTOMER (customer_id),
			INDEX IDX_DEMAND_CITY (city_id), INDEX IDX_DEMAND_CATEGORY (category_id), PRIMARY KEY(id),
			CONSTRAINT FK_DEMAND_CUSTOMER FOREIGN KEY (customer_id) REFERENCES `user` (id),
			CONSTRAINT FK_DEMAND_CITY FOREIGN KEY (city_id) REFERENCES city (id),
			CONSTRAINT FK_DEMAND_CATEGORY FOREIGN KEY (category_id) REFERENCES category_service (id)
		) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
	}

	public function down(Schema $schema): void
	{
		$this->abortIf((int) $this->connection->fetchOne('SELECT COUNT(*) FROM demand') > 0,
			'Export and remove demands explicitly before rolling back this migration.');
		$this->addSql('DROP TABLE demand');
		$this->addSql('DROP TABLE customer_token');
		$this->addSql("UPDATE `user` SET street_no = COALESCE(street_no, ''), city = COALESCE(city, ''), zip_code = COALESCE(zip_code, '')");
		$this->addSql('ALTER TABLE `user` DROP customer_session_version, MODIFY street_no VARCHAR(100) NOT NULL, MODIFY city VARCHAR(100) NOT NULL, MODIFY zip_code VARCHAR(100) NOT NULL');
		$this->addSql('DROP INDEX UNIQ_CUSTOMER_EMAIL ON `user`');
	}
}
