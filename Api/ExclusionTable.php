<?php

declare(strict_types=1);

namespace GoogleShoppingXml\Api;

use Propel\Runtime\Propel;
use Thelia\Model\Map\ProductSaleElementsTableMap;

/**
 * Tells whether the table of the combination exclusions exists.
 *
 * The API extension joins that table to every read of the combinations. When the module is flagged active but its
 * tables were never created, the join would answer a 500.
 *
 * Only a positive answer is kept, for the rest of the process (one metadata query per HTTP request under PHP-FPM).
 * A missing table is looked up again at each call, so the module works as soon as the table is created.
 */
final class ExclusionTable
{
    public const NAME = 'googleshoppingxml_product_excluded';

    private static bool $exists = false;

    private static ?\Closure $lookup = null;

    public static function exists(): bool
    {
        if (self::$exists) {
            return true;
        }

        return self::$exists = (self::$lookup ?? self::queryDatabase(...))();
    }

    /**
     * For the tests: replaces the query on the database, and forgets the answer already kept.
     */
    public static function replaceLookup(?\Closure $lookup): void
    {
        self::$lookup = $lookup;
        self::$exists = false;
    }

    private static function queryDatabase(): bool
    {
        $statement = Propel::getConnection(ProductSaleElementsTableMap::DATABASE_NAME)->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $statement->execute([self::NAME]);

        return false !== $statement->fetchColumn();
    }
}
