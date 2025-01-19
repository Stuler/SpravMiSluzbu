CREATE TABLE provider_service_category
(
	id                  INT AUTO_INCREMENT                     NOT NULL,
	provider_id         INT                                    NOT NULL,
	service_category_id INT                                    NOT NULL,
	date_created        DATETIME     DEFAULT CURRENT_TIMESTAMP NOT NULL,
	date_modified       DATETIME     DEFAULT NULL,
	date_deleted        DATETIME     DEFAULT NULL,
	created_by          VARCHAR(255) DEFAULT NULL,
	deleted_by          VARCHAR(255) DEFAULT NULL,
	INDEX IDX_923FF3E1A53A8AA (provider_id),
	INDEX IDX_923FF3E1DEDCBB4E (service_category_id),
	PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8
  COLLATE `utf8_unicode_ci`
  ENGINE = InnoDB;
ALTER TABLE provider_service_category
	ADD CONSTRAINT FK_923FF3E1A53A8AA FOREIGN KEY (provider_id) REFERENCES provider (id);
ALTER TABLE provider_service_category
	ADD CONSTRAINT FK_923FF3E1DEDCBB4E FOREIGN KEY (service_category_id) REFERENCES category_service (id);
ALTER TABLE provider
	DROP FOREIGN KEY FK_92C4739C98260155;
ALTER TABLE provider
	DROP FOREIGN KEY FK_92C4739CDEDCBB4E;
DROP INDEX IDX_92C4739C98260155 ON provider;
DROP INDEX IDX_92C4739CDEDCBB4E ON provider;
ALTER TABLE provider
	DROP region_id,
	DROP service_category_id;
