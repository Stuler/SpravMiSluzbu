CREATE TABLE district
(
	id          INT AUTO_INCREMENT   NOT NULL,
	name        VARCHAR(255)         NOT NULL,
	veh_reg_num VARCHAR(255)         NOT NULL,
	code        SMALLINT             NOT NULL,
	region_id   INT                  NOT NULL,
	`use`       TINYINT(1) DEFAULT 1 NOT NULL,
	PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8
  COLLATE `utf8_unicode_ci`
  ENGINE = InnoDB;
CREATE TABLE region
(
	id       INT AUTO_INCREMENT   NOT NULL,
	name     VARCHAR(255)         NOT NULL,
	shortcut VARCHAR(2)           NOT NULL,
	`use`    TINYINT(1) DEFAULT 1 NOT NULL,
	PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8
  COLLATE `utf8_unicode_ci`
  ENGINE = InnoDB;
CREATE TABLE village
(
	id          INT AUTO_INCREMENT   NOT NULL,
	fullname    VARCHAR(255)         NOT NULL,
	shortname   VARCHAR(255)         NOT NULL,
	zip         VARCHAR(6)           NOT NULL,
	district_id INT                  NOT NULL,
	region_id   INT                  NOT NULL,
	`use`       TINYINT(1) DEFAULT 1 NOT NULL,
	PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8
  COLLATE `utf8_unicode_ci`
  ENGINE = InnoDB;
ALTER TABLE category_service
	ADD CONSTRAINT FK_2645DAACDE12AB56 FOREIGN KEY (created_by) REFERENCES user (id);
CREATE INDEX IDX_2645DAACDE12AB56 ON category_service (created_by);
ALTER TABLE user
	ADD CONSTRAINT FK_8D93D649DE12AB56 FOREIGN KEY (created_by) REFERENCES user (id);
CREATE INDEX IDX_8D93D649DE12AB56 ON user (created_by);

INSERT INTO `region` (`id`, `name`, `shortcut`, `use`)
VALUES (1, 'Banskobystrický kraj', 'BC', 1),
	   (2, 'Bratislavský kraj', 'BL', 1),
	   (3, 'Košický kraj', 'KI', 1),
	   (4, 'Nitriansky kraj', 'NI', 1),
	   (5, 'Prešovský kraj', 'PV', 1),
	   (6, 'Trenčiansky kraj', 'TC', 1),
	   (7, 'Trnavský kraj', 'TA', 1),
	   (8, 'Žilinský kraj', 'ZI', 1);

INSERT INTO `district` (`id`, `name`, `veh_reg_num`, `code`, `region_id`, `use`)
VALUES (1, 'Bánovce nad Bebravou', 'BN', 301, 6, 1),
	   (2, 'Banská Bystrica', 'BB', 601, 1, 1),
	   (3, 'Banská Štiavnica', 'BS', 602, 1, 1),
	   (4, 'Bardejov', 'BJ', 701, 5, 1),
	   (5, 'Bratislava I', 'BA, BL', 101, 2, 1),
	   (6, 'Bratislava II', 'BA, BL', 102, 2, 1),
	   (7, 'Bratislava III', 'BA, BL', 103, 2, 1),
	   (8, 'Bratislava IV', 'BA, BL', 104, 2, 1),
	   (9, 'Bratislava V', 'BA, BL', 105, 2, 1),
	   (10, 'Brezno', 'BR', 603, 1, 1),
	   (11, 'Bytča', 'BY', 501, 8, 1),
	   (12, 'Čadca', 'CA', 502, 8, 1),
	   (13, 'Detva', 'DT', 604, 1, 1),
	   (14, 'Dolný Kubín', 'DK', 503, 8, 1),
	   (15, 'Dunajská Streda', 'DS', 201, 7, 1),
	   (16, 'Galanta', 'GA', 202, 7, 1),
	   (17, 'Gelnica', 'GL', 801, 3, 1),
	   (18, 'Hlohovec', 'HC', 203, 7, 1),
	   (19, 'Humenné', 'HE', 702, 5, 1),
	   (20, 'Ilava', 'IL', 302, 6, 1),
	   (21, 'Kežmarok', 'KK', 703, 5, 1),
	   (22, 'Komárno', 'KN', 401, 4, 1),
	   (23, 'Košice I', 'KE', 802, 3, 1),
	   (24, 'Košice II', 'KE', 803, 3, 1),
	   (25, 'Košice III', 'KE', 804, 3, 1),
	   (26, 'Košice IV', 'KE', 805, 3, 1),
	   (27, 'Košice-okolie', 'KS', 806, 3, 1),
	   (28, 'Krupina', 'KA', 605, 1, 1),
	   (29, 'Kysucké Nové Mesto', 'KM', 504, 8, 1),
	   (30, 'Levice', 'LV', 402, 4, 1),
	   (31, 'Levoča', 'LE', 704, 5, 1),
	   (32, 'Liptovský Mikuláš', 'LM', 505, 8, 1),
	   (33, 'Lučenec', 'LC', 606, 1, 1),
	   (34, 'Malacky', 'MA', 106, 2, 1),
	   (35, 'Martin', 'MT', 506, 8, 1),
	   (36, 'Medzilaborce', 'ML', 705, 5, 1),
	   (37, 'Michalovce', 'MI', 807, 3, 1),
	   (38, 'Myjava', 'MY', 303, 6, 1),
	   (39, 'Námestovo', 'NO', 507, 8, 1),
	   (40, 'Nitra', 'NR', 403, 4, 1),
	   (41, 'Nové Mesto nad Váhom', 'NM', 304, 6, 1),
	   (42, 'Nové Zámky', 'NZ', 404, 4, 1),
	   (43, 'Partizánske', 'PE', 305, 6, 1),
	   (44, 'Pezinok', 'PK', 107, 2, 1),
	   (45, 'Piešťany', 'PN', 204, 7, 1),
	   (46, 'Poltár', 'PT', 607, 1, 1),
	   (47, 'Poprad', 'PP', 706, 5, 1),
	   (48, 'Považská Bystrica', 'PB', 306, 6, 1),
	   (49, 'Prešov', 'PO', 707, 5, 1),
	   (50, 'Prievidza', 'PD', 307, 6, 1),
	   (51, 'Púchov', 'PU', 308, 6, 1),
	   (52, 'Revúca', 'RA', 608, 1, 1),
	   (53, 'Rimavská Sobota', 'RS', 609, 1, 1),
	   (54, 'Rožňava', 'RV', 808, 3, 1),
	   (55, 'Ružomberok', 'RK', 508, 8, 1),
	   (56, 'Sabinov', 'SB', 708, 5, 1),
	   (57, 'Senec', 'SC', 108, 2, 1),
	   (58, 'Senica', 'SE', 205, 7, 1),
	   (59, 'Skalica', 'SI', 206, 7, 1),
	   (60, 'Snina', 'SV', 709, 5, 1),
	   (61, 'Sobrance', 'SO', 809, 3, 1),
	   (62, 'Spišská Nová Ves', 'SN', 810, 3, 1),
	   (63, 'Stará Ľubovňa', 'SL', 710, 5, 1),
	   (64, 'Stropkov', 'SP', 711, 5, 1),
	   (65, 'Svidník', 'SK', 712, 5, 1),
	   (66, 'Šaľa', 'SA', 405, 4, 1),
	   (67, 'Topoľčany', 'TO', 406, 4, 1),
	   (68, 'Trebišov', 'TV', 811, 3, 1),
	   (69, 'Trenčín', 'TN', 309, 6, 1),
	   (70, 'Trnava', 'TT', 207, 7, 1),
	   (71, 'Turčianske Teplice', 'TR', 509, 8, 1),
	   (72, 'Tvrdošín', 'TS', 510, 8, 1),
	   (73, 'Veľký Krtíš', 'VK', 610, 1, 1),
	   (74, 'Vranov nad Topľou', 'VT', 713, 5, 1),
	   (75, 'Zlaté Moravce', 'ZM', 407, 4, 1),
	   (76, 'Zvolen', 'ZV', 611, 1, 1),
	   (77, 'Žarnovica', 'ZC', 612, 1, 1),
	   (78, 'Žiar nad Hronom', 'ZH', 613, 1, 1),
	   (79, 'Žilina', 'ZA', 511, 8, 1);

-- Insert categories for "Interiér"
INSERT INTO `category_service` (`parent_id`, `name`, `description`, `date_created`, `date_modified`, `created_by`,
								`deleted_by`)
VALUES (4, 'Oprava zariadení', '', '2024-12-30 19:17:46', NULL, 1, NULL),
	   (4, 'Čistenie', '', '2024-12-30 19:17:46', NULL, 1, NULL),
	   (4, 'Stavebné práce a prerábanie', NULL, '2024-12-30 19:17:46', NULL, 1, NULL),
	   (4, 'Elektroinštalácia', '', '2024-12-30 19:17:46', NULL, 1, NULL),
	   (4, 'Podlahy', '', '2024-12-30 19:17:46', NULL, 1, NULL),
	   (4, 'Teplo, ventilácia a klimatizácia', '', '2024-12-30 19:17:46', NULL, 1, NULL),
	   (4, 'Upratovanie', '', '2024-12-30 19:17:46', NULL, 1, NULL),
	   (4, 'Maľovanie', '', '2024-12-30 19:17:46', NULL, 1, NULL),
	   (4, 'Kúrenie a voda', '', '2024-12-30 19:17:46', NULL, 1, NULL),
	   (4, 'Výroba a skladanie nábytku', '', '2024-12-30 19:17:46', NULL, 1, NULL),
	   (4, 'Okná a dvere', '', '2024-12-30 19:17:46', NULL, 1, NULL),
	   (4, 'Kúpeľňa', '', '2024-12-30 19:17:46', NULL, 1, NULL),
	   (4, 'Kuchyňa', '', '2024-12-30 19:17:46', NULL, 1, NULL),
	   (4, 'Odpad', '', '2024-12-30 19:17:46', NULL, 1, NULL);

-- Insert categories for "Exteriér"
INSERT INTO `category_service` (`parent_id`, `name`, `description`, `date_created`, `date_modified`, `created_by`,
								`deleted_by`)
VALUES (5, 'Brány a dvere', '', '2024-12-30 19:17:46', NULL, 1, NULL),
	   (5, 'Príjazdové cesty, chodníky', '', '2024-12-30 19:17:46', NULL, 1, NULL),
	   (5, 'betónové dlažby', '', '2024-12-30 19:17:46', NULL, 1, NULL),
	   (5, 'Garážové brány', '', '2024-12-30 19:17:46', NULL, 1, NULL),
	   (5, 'Omietky, fasády', '', '2024-12-30 19:17:46', NULL, 1, NULL),
	   (5, 'Odkvap (čistenie, oprava, inštalácia)', '', '2024-12-30 19:17:46', NULL, 1, NULL),
	   (5, 'Stavebné práce', '', '2024-12-30 19:17:46', NULL, 1, NULL),
	   (5, 'Strecha', '', '2024-12-30 19:17:46', NULL, 1, NULL),
	   (5, 'Obklady', '', '2024-12-30 19:17:46', NULL, 1, NULL),
	   (5, 'Zateplenie', '', '2024-12-30 19:17:46', NULL, 1, NULL),
	   (5, 'Komíny', '', '2024-12-30 19:17:46', NULL, 1, NULL);

-- Insert categories for "Záhrada"
INSERT INTO `category_service` (`parent_id`, `name`, `description`, `date_created`, `date_modified`, `created_by`,
								`deleted_by`)
VALUES (6, 'Terasy', '', '2024-12-30 19:17:46', NULL, 1, NULL),
	   (6, 'Oplotenie', '', '2024-12-30 19:17:46', NULL, 1, NULL),
	   (6, 'Zameranie pozemku', '', '2024-12-30 19:17:46', NULL, 1, NULL),
	   (6, 'Záhradná architektúra', '', '2024-12-30 19:17:46', NULL, 1, NULL),
	   (6, 'Trávnik a záhradné práce', '', '2024-12-30 19:17:46', NULL, 1, NULL),
	   (6, 'Pestovanie', '', '2024-12-30 19:17:46', NULL, 1, NULL),
	   (6, 'Zavlažovanie', '', '2024-12-30 19:17:46', NULL, 1, NULL),
	   (6, 'Záhradné domy a prístrešky', '', '2024-12-30 19:17:46', NULL, 1, NULL),
	   (6, 'Bazény', '', '2024-12-30 19:17:46', NULL, 1, NULL),
	   (6, 'Oddychové zóny', '', '2024-12-30 19:17:46', NULL, 1, NULL),
	   (6, 'Stromy', '', '2024-12-30 19:17:46', NULL, 1, NULL),
	   (6, 'Stojany, záhradné lavičky, koše', '', '2024-12-30 19:17:46', NULL, 1, NULL),
	   (6, 'Štrky, piesky, okrasné kamene', '', '2024-12-30 19:17:46', NULL, 1, NULL),
	   (6, 'Záhradný nábytok', '', '2024-12-30 19:17:46', NULL, 1, NULL);

-- Insert categories for "Ostatné"
INSERT INTO `category_service` (`parent_id`, `name`, `description`, `date_created`, `date_modified`, `created_by`,
								`deleted_by`)
VALUES (7, 'Výstavba domov', '', '2024-12-30 19:17:46', NULL, 1, NULL),
	   (7, 'Vlhkosť a izolácia', '', '2024-12-30 19:17:46', NULL, 1, NULL),
	   (7, 'Zámočníctvo', '', '2024-12-30 19:17:46', NULL, 1, NULL),
	   (7, 'Sťahovanie', '', '2024-12-30 19:17:46', NULL, 1, NULL),
	   (7, 'Ochrana pred škodcami', '', '2024-12-30 19:17:46', NULL, 1, NULL),
	   (7, 'Zabezpečovacie systémy', '', '2024-12-30 19:17:46', NULL, 1, NULL),
	   (7, 'Železiarske práce', '', '2024-12-30 19:17:46', NULL, 1, NULL),
	   (7, 'Práca s drevom', '', '2024-12-30 19:17:46', NULL, 1, NULL),
	   (7, 'Osvetlenie', '', '2024-12-30 19:17:46', NULL, 1, NULL),
	   (7, 'Inteligentná domácnosť', '', '2024-12-30 19:17:46', NULL, 1, NULL),
	   (7, 'Tvorivá práca', '', '2024-12-30 19:17:46', NULL, 1, NULL),
	   (7, 'Domáci miláčikovia', '', '2024-12-30 19:17:46', NULL, 1, NULL),
	   (7, 'IT a Design', '', '2024-12-30 19:17:46', NULL, 1, NULL),
	   (7, 'Deti', '', '2024-12-30 19:17:46', NULL, 1, NULL);

INSERT INTO `login_role` (`name`)
VALUES ('provider');
