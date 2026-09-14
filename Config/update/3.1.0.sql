
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- googleshoppingxml_feed_country
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `googleshoppingxml_feed_country`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `feed_id` INTEGER NOT NULL,
    `country_id` INTEGER NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `unique_googleshoppingxml_feed_country` (`feed_id`, `country_id`),
    INDEX `fi_googleshoppingxml_feed_country_country_id` (`country_id`),
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

# This restores the fkey checks, after having unset them earlier
SET FOREIGN_KEY_CHECKS = 1;
