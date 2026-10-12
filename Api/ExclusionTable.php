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

    private static ?\Closure $connection = null;

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

    /**
     * For the tests: replaces the connection the metadata query runs on, to exercise the query itself.
     */
    public static function replaceConnection(?\Closure $connection): void
    {
        self::$connection = $connection;
        self::$exists = false;
    }

    private static function queryDatabase(): bool
    {
        $connection = null !== self::$connection
            ? (self::$connection)()
            : Propel::getConnection(ProductSaleElementsTableMap::DATABASE_NAME);

        // COUNT(*) always returns a row. The Propel statement wrapper casts the "no row" answer of fetchColumn()
        // (false) to an empty string, so it cannot be compared to false.
        $statement = $connection->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $statement->execute([self::NAME]);

        return 1 === (int) $statement->fetchColumn();
    }
}
