CREATE TABLE provider
(
	id                  INT AUTO_INCREMENT                 NOT NULL,
	region_id           INT      DEFAULT NULL,
	service_category_id INT      DEFAULT NULL,
	city_id             INT      DEFAULT NULL,
	state_provider_id   INT      DEFAULT NULL,
	created_by          INT      DEFAULT NULL,
	company_name        VARCHAR(255)                       NOT NULL,
	contact_name        VARCHAR(64)                        NOT NULL,
	contact_surname     VARCHAR(64)                        NOT NULL,
	contact_title       VARCHAR(32)                        NOT NULL,
	email               VARCHAR(100)                       NOT NULL,
	phone_number        VARCHAR(100)                       NOT NULL,
	ico                 VARCHAR(100)                       NOT NULL,
	dic                 VARCHAR(100)                       NOT NULL,
	password            VARCHAR(100)                       NOT NULL,
	date_last_login     DATETIME DEFAULT CURRENT_TIMESTAMP,
	note                LONGTEXT DEFAULT NULL,
	street_no           VARCHAR(100)                       NOT NULL,
	zip_code            VARCHAR(100)                       NOT NULL,
	date_created        DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
	date_modified       DATETIME DEFAULT CURRENT_TIMESTAMP,
	date_deleted        DATETIME DEFAULT NULL,
	deleted_by          INT      DEFAULT NULL,
	INDEX IDX_92C4739C98260155 (region_id),
	INDEX IDX_92C4739CDEDCBB4E (service_category_id),
	INDEX IDX_92C4739C8BAC62AF (city_id),
	INDEX IDX_92C4739CC1778CF7 (state_provider_id),
	INDEX IDX_92C4739CDE12AB56 (created_by),
	PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8
  COLLATE `utf8_unicode_ci`
  ENGINE = InnoDB;
CREATE TABLE state_provider
(
	id            INT AUTO_INCREMENT                 NOT NULL,
	created_by    INT      DEFAULT NULL,
	name          VARCHAR(32)                        NOT NULL,
	date_created  DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
	date_modified DATETIME DEFAULT CURRENT_TIMESTAMP,
	date_deleted  DATETIME DEFAULT NULL,
	deleted_by    INT      DEFAULT NULL,
	INDEX IDX_AD37153BDE12AB56 (created_by),
	PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8
  COLLATE `utf8_unicode_ci`
  ENGINE = InnoDB;
CREATE TABLE state_user
(
	id            INT AUTO_INCREMENT                 NOT NULL,
	created_by    INT      DEFAULT NULL,
	name          VARCHAR(32)                        NOT NULL,
	date_created  DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
	date_modified DATETIME DEFAULT CURRENT_TIMESTAMP,
	date_deleted  DATETIME DEFAULT NULL,
	deleted_by    INT      DEFAULT NULL,
	INDEX IDX_19705F8FDE12AB56 (created_by),
	PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8
  COLLATE `utf8_unicode_ci`
  ENGINE = InnoDB;
ALTER TABLE city
	ADD CONSTRAINT FK_2D5B0234B08FA272 FOREIGN KEY (district_id) REFERENCES district (id);
ALTER TABLE city
	ADD CONSTRAINT FK_2D5B023498260155 FOREIGN KEY (region_id) REFERENCES region (id);
ALTER TABLE provider
	ADD CONSTRAINT FK_92C4739C98260155 FOREIGN KEY (region_id) REFERENCES region (id);
ALTER TABLE provider
	ADD CONSTRAINT FK_92C4739CDEDCBB4E FOREIGN KEY (service_category_id) REFERENCES category_service (id);
ALTER TABLE provider
	ADD CONSTRAINT FK_92C4739C8BAC62AF FOREIGN KEY (city_id) REFERENCES city (id);
ALTER TABLE provider
	ADD CONSTRAINT FK_92C4739CC1778CF7 FOREIGN KEY (state_provider_id) REFERENCES state_provider (id);
ALTER TABLE provider
	ADD CONSTRAINT FK_92C4739CDE12AB56 FOREIGN KEY (created_by) REFERENCES user (id);
ALTER TABLE state_provider
	ADD CONSTRAINT FK_AD37153BDE12AB56 FOREIGN KEY (created_by) REFERENCES user (id);
ALTER TABLE state_user
	ADD CONSTRAINT FK_19705F8FDE12AB56 FOREIGN KEY (created_by) REFERENCES user (id);
ALTER TABLE district
	CHANGE region_id region_id INT DEFAULT NULL;
ALTER TABLE district
	ADD CONSTRAINT FK_31C1548798260155 FOREIGN KEY (region_id) REFERENCES region (id);
CREATE INDEX IDX_31C1548798260155 ON district (region_id);
ALTER TABLE user
	ADD state_user_id INT DEFAULT NULL,
	DROP state;
ALTER TABLE user
	ADD CONSTRAINT FK_8D93D649E10EA933 FOREIGN KEY (state_user_id) REFERENCES state_user (id);
CREATE INDEX IDX_8D93D649E10EA933 ON user (state_user_id);

INSERT INTO `state_user` (`id`, `created_by`, `name`, `date_created`, `date_modified`, `date_deleted`, `deleted_by`)
VALUES (1, 1, 'fresh', '2025-01-04 09:05:30', '2025-01-04 09:05:30', NULL, NULL),
	   (2, 1, 'activated', '2025-01-04 09:05:42', '2025-01-04 09:05:42', NULL, NULL),
	   (3, 1, 'blocked', '2025-01-04 09:05:47', '2025-01-04 09:05:47', NULL, NULL);

INSERT INTO `state_provider` (`id`, `created_by`, `name`, `date_created`, `date_modified`, `date_deleted`, `deleted_by`)
VALUES (1, 1, 'fresh', '2025-01-04 09:05:30', '2025-01-04 09:05:30', NULL, NULL),
	   (2, 1, 'activated', '2025-01-04 09:05:42', '2025-01-04 09:05:42', NULL, NULL),
	   (3, 1, 'blocked', '2025-01-04 09:05:47', '2025-01-04 09:05:47', NULL, NULL);
