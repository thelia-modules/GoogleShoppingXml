SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- googleshoppingxml_product_excluded
-- The combinations kept out of every feed. Never dropped: it holds the merchant's choices.
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `googleshoppingxml_product_excluded`
(
    `pse_id` INTEGER NOT NULL,
    `is_excluded` TINYINT(4) DEFAULT 0,
    PRIMARY KEY (`pse_id`),
    CONSTRAINT `fk_googleshoppingxml_product_excluded_pse_id`
        FOREIGN KEY (`pse_id`)
        REFERENCES `product_sale_elements` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
