
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
    `brand_value` VARCHAR(255),
    `id_related_attribute` INTEGER,
    `id_related_feature` INTEGER,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `unique_googleshoppingxml_google_field_association_google_field` (`google_field`),
    INDEX `fi_googleshoppingxml_gl_field_association_id_attribute` (`id_related_attribute`),
    INDEX `fi_googleshoppingxml_gl_field_association_id_feature` (`id_related_feature`),
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