
-- ---------------------------------------------------------------------
-- googleshoppingxml_ignore_category
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `googleshoppingxml_ignore_category`;

CREATE TABLE `googleshoppingxml_ignore_category`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `is_exportable` INTEGER DEFAULT 1 NOT NULL,
    `category_id` INTEGER NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `fi_googleshoppingxml_category_is_exportable_id` (`category_id`),
    CONSTRAINT `fk_googleshoppingxml_category_is_exportable_id`
        FOREIGN KEY (`category_id`)
            REFERENCES `category` (`id`)
            ON UPDATE RESTRICT
            ON DELETE CASCADE
) ENGINE=InnoDB;

# This restores the fkey checks, after having unset them earlier
SET FOREIGN_KEY_CHECKS = 1;