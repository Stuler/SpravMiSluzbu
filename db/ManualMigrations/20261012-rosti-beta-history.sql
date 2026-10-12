-- One-time repair of the inspected Rošti beta database, NOT a deploy migration.
-- Preconditions: private database backup; empty doctrine_migrations; schema
-- reviewed against the first ten migrations; provider's part of
-- Version20250214134949 already exists; all six deleted_by columns below are
-- nullable VARCHAR(255), contain only NULL, and have no foreign key on them.
-- Do not run on production, another database, or replay after successful repair.
-- MySQL/MariaDB DDL commits implicitly. Stop on the first error; record history
-- only after ALL six schema repairs succeed. Do not use migrations:version --add --all.

ALTER TABLE category_service MODIFY deleted_by INT DEFAULT NULL, ADD INDEX IDX_2645DAAC1F6FA0AF (deleted_by), ADD CONSTRAINT FK_2645DAAC1F6FA0AF FOREIGN KEY (deleted_by) REFERENCES user (id);
ALTER TABLE provider_region MODIFY deleted_by INT DEFAULT NULL, ADD INDEX IDX_585312E61F6FA0AF (deleted_by), ADD CONSTRAINT FK_585312E61F6FA0AF FOREIGN KEY (deleted_by) REFERENCES user (id);
ALTER TABLE provider_service_category MODIFY deleted_by INT DEFAULT NULL, ADD INDEX IDX_923FF3E11F6FA0AF (deleted_by), ADD CONSTRAINT FK_923FF3E11F6FA0AF FOREIGN KEY (deleted_by) REFERENCES user (id);
ALTER TABLE state_provider MODIFY deleted_by INT DEFAULT NULL, ADD INDEX IDX_AD37153B1F6FA0AF (deleted_by), ADD CONSTRAINT FK_AD37153B1F6FA0AF FOREIGN KEY (deleted_by) REFERENCES user (id);
ALTER TABLE state_user MODIFY deleted_by INT DEFAULT NULL, ADD INDEX IDX_19705F8F1F6FA0AF (deleted_by), ADD CONSTRAINT FK_19705F8F1F6FA0AF FOREIGN KEY (deleted_by) REFERENCES user (id);
ALTER TABLE user MODIFY deleted_by INT DEFAULT NULL, ADD INDEX IDX_8D93D6491F6FA0AF (deleted_by), ADD CONSTRAINT FK_8D93D6491F6FA0AF FOREIGN KEY (deleted_by) REFERENCES user (id);

-- Baseline adoption time, not the original historical execution timestamp.
-- CHAR(92) avoids dependence on the server's NO_BACKSLASH_ESCAPES SQL mode.
INSERT INTO doctrine_migrations (version, executed_at, execution_time) VALUES
(CONCAT('Database', CHAR(92), 'Migrations', CHAR(92), 'Version20240601121738'), CURRENT_TIMESTAMP, NULL),
(CONCAT('Database', CHAR(92), 'Migrations', CHAR(92), 'Version20240601121953'), CURRENT_TIMESTAMP, NULL),
(CONCAT('Database', CHAR(92), 'Migrations', CHAR(92), 'Version20240610174918'), CURRENT_TIMESTAMP, NULL),
(CONCAT('Database', CHAR(92), 'Migrations', CHAR(92), 'Version20240804071848'), CURRENT_TIMESTAMP, NULL),
(CONCAT('Database', CHAR(92), 'Migrations', CHAR(92), 'Version20240804100814'), CURRENT_TIMESTAMP, NULL),
(CONCAT('Database', CHAR(92), 'Migrations', CHAR(92), 'Version20240806190207'), CURRENT_TIMESTAMP, NULL),
(CONCAT('Database', CHAR(92), 'Migrations', CHAR(92), 'Version20240921092008'), CURRENT_TIMESTAMP, NULL),
(CONCAT('Database', CHAR(92), 'Migrations', CHAR(92), 'Version20250104080341'), CURRENT_TIMESTAMP, NULL),
(CONCAT('Database', CHAR(92), 'Migrations', CHAR(92), 'Version20250213051018'), CURRENT_TIMESTAMP, NULL),
(CONCAT('Database', CHAR(92), 'Migrations', CHAR(92), 'Version20250214134949'), CURRENT_TIMESTAMP, NULL);
