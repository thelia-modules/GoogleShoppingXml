
# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- googleshoppingxml_taxonomy
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `googleshoppingxml_taxonomy`;

CREATE TABLE `googleshoppingxml_taxonomy`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `thelia_category_id` INTEGER NOT NULL,
    `google_category` VARCHAR(255) NOT NULL,
    `lang_id` INTEGER NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `googleshoppingxml_taxonomy_unique_couple_thelia_category_id_lang` (`thelia_category_id`, `lang_id`),
    INDEX `FI_googleshoppingxml_thelia_lang_id` (`lang_id`),
    CONSTRAINT `fk_googleshoppingxml_thelia_category_id`
        FOREIGN KEY (`thelia_category_id`)
        REFERENCES `category` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_googleshoppingxml_thelia_lang_id`
        FOREIGN KEY (`lang_id`)
        REFERENCES `lang` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- googleshoppingxml_feed
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `googleshoppingxml_feed`;

CREATE TABLE `googleshoppingxml_feed`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `label` VARCHAR(255),
    `lang_id` INTEGER,
    `currency_id` INTEGER,
    PRIMARY KEY (`id`),
    INDEX `FI_googleshoppingxml_feed_lang_id` (`lang_id`),
    INDEX `FI_googleshoppingxml_feed_id` (`currency_id`),
    CONSTRAINT `fk_googleshoppingxml_feed_lang_id`
        FOREIGN KEY (`lang_id`)
        REFERENCES `lang` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_googleshoppingxml_feed_id`
        FOREIGN KEY (`currency_id`)
        REFERENCES `currency` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- googleshoppingxml_feed_country
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `googleshoppingxml_feed_country`;

CREATE TABLE `googleshoppingxml_feed_country`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `feed_id` INTEGER,
    `country_id` INTEGER,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `googleshoppingxml_feed_country_unique_couple_feed_id_country_id` (`feed_id`, `country_id`),
    INDEX `FI_googleshoppingxml_feed_country_country_id` (`country_id`),
    CONSTRAINT `fk_googleshoppingxml_feed_country_feed_id`
        FOREIGN KEY (`feed_id`)
        REFERENCES `googleshoppingxml_feed` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_googleshoppingxml_feed_country_country_id`
        FOREIGN KEY (`country_id`)
        REFERENCES `country` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- googleshoppingxml_google_field_association
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `googleshoppingxml_google_field_association`;

CREATE TABLE `googleshoppingxml_google_field_association`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `google_field` VARCHAR(255) NOT NULL,
    `association_type` INTEGER NOT NULL,
    `fixed_value` VARCHAR(255),
    `id_related_attribute` INTEGER,
    `id_related_feature` INTEGER,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `unique_googleshoppingxml_google_field_association_google_field` (`google_field`),
    INDEX `FI_googleshoppingxml_gl_field_association_id_attribute` (`id_related_attribute`),
    INDEX `FI_googleshoppingxml_gl_field_association_id_feature` (`id_related_feature`),
    CONSTRAINT `fk_googleshoppingxml_gl_field_association_id_attribute`
        FOREIGN KEY (`id_related_attribute`)
        REFERENCES `attribute` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_googleshoppingxml_gl_field_association_id_feature`
        FOREIGN KEY (`id_related_feature`)
        REFERENCES `feature` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

# This restores the fkey checks, after having unset them earlier
SET FOREIGN_KEY_CHECKS = 1;
