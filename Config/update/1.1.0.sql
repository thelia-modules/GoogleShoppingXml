
# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- googleshoppingxml_log
-- ---------------------------------------------------------------------

CREATE TABLE `googleshoppingxml_log`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `feed_id` INTEGER NOT NULL,
    `separation` TINYINT(1) NOT NULL,
    `level` INTEGER NOT NULL,
    `pse_id` INTEGER,
    `message` TEXT NOT NULL,
    `help` TEXT,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `FI_googleshoppingxml_log_feed_id` (`feed_id`),
    INDEX `FI_googleshoppingxml_log_pse_id` (`pse_id`),
    CONSTRAINT `fk_googleshoppingxml_log_feed_id`
        FOREIGN KEY (`feed_id`)
        REFERENCES `googleshoppingxml_feed` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_googleshoppingxml_log_pse_id`
        FOREIGN KEY (`pse_id`)
        REFERENCES `product_sale_elements` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

# This restores the fkey checks, after having unset them earlier
SET FOREIGN_KEY_CHECKS = 1;
