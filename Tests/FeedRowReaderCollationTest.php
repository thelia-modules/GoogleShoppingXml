<?php

declare(strict_types=1);

namespace GoogleShoppingXml\Tests;

use GoogleShoppingXml\Feed\FeedRow;
use GoogleShoppingXml\Feed\FeedRowReader;
use PHPUnit\Framework\TestCase;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Connection\ConnectionWrapper;
use Propel\Runtime\Connection\PdoConnection;

require_once __DIR__.'/../Feed/FeedRow.php';
require_once __DIR__.'/../Feed/FeedRowReader.php';

/*
 * Needs an EMPTY throwaway MySQL or MariaDB database: the test creates the few tables the reader queries, then drops
 * them. It refuses to run on a database that already has a `product` table.
 *
 *   GOOGLESHOPPINGXML_TEST_DSN='mysql:host=db;dbname=gsx_collation_test' \
 *   GOOGLESHOPPINGXML_TEST_USER=root GOOGLESHOPPINGXML_TEST_PASSWORD=root \
 *   vendor/bin/phpunit vendor/thelia/modules/GoogleShoppingXml/Tests/FeedRowReaderCollationTest.php
 *
 * Skipped when GOOGLESHOPPINGXML_TEST_DSN is not set.
 */
final class FeedRowReaderCollationTest extends TestCase
{
    /**
     * The tables of a shop created before utf8mb4_unicode_ci became the default: every text column in
     * utf8mb4_general_ci, while the connection works in utf8mb4_unicode_ci.
     */
    private const TABLES = [
        'product' => 'id INT PRIMARY KEY, ref VARCHAR(255), visible TINYINT, tax_rule_id INT, brand_id INT NULL',
        'product_i18n' => 'id INT, locale VARCHAR(10), title VARCHAR(255), chapo TEXT, description LONGTEXT, PRIMARY KEY (id, locale)',
        'product_sale_elements' => 'id INT PRIMARY KEY, product_id INT, ref VARCHAR(255), quantity FLOAT, ean_code VARCHAR(255), weight FLOAT, promo TINYINT',
        'brand_i18n' => 'id INT, locale VARCHAR(10), title VARCHAR(255), PRIMARY KEY (id, locale)',
        'product_category' => 'product_id INT, category_id INT, default_category TINYINT, PRIMARY KEY (product_id, category_id)',
        'product_price' => 'product_sale_elements_id INT, currency_id INT, price DECIMAL(16,6), promo_price DECIMAL(16,6), from_default_currency TINYINT, PRIMARY KEY (product_sale_elements_id, currency_id)',
        'currency' => 'id INT PRIMARY KEY, by_default TINYINT',
        'rewriting_url' => 'id INT PRIMARY KEY, url VARCHAR(255), view VARCHAR(255), view_id VARCHAR(255), view_locale VARCHAR(255), redirected INT NULL,'
            .' INDEX idx_rewriting_url (view_locale, view, view_id, redirected)',
        'googleshoppingxml_product_excluded' => 'pse_id INT PRIMARY KEY, is_excluded TINYINT',
        'attribute_combination' => 'attribute_id INT, attribute_av_id INT, product_sale_elements_id INT',
        'attribute_av_i18n' => 'id INT, locale VARCHAR(10), title VARCHAR(255)',
        'product_image' => 'id INT PRIMARY KEY, product_id INT, visible TINYINT, position INT',
        'product_image_i18n' => 'id INT, locale VARCHAR(10), file VARCHAR(255)',
        'lang' => 'id INT PRIMARY KEY, locale VARCHAR(10), by_default TINYINT',
        'product_sale_elements_product_image' => 'product_sale_elements_id INT, product_image_id INT',
    ];

    private ?ConnectionInterface $connection = null;

    protected function setUp(): void
    {
        $dsn = getenv('GOOGLESHOPPINGXML_TEST_DSN');
        if (false === $dsn || '' === $dsn) {
            self::markTestSkipped('GOOGLESHOPPINGXML_TEST_DSN is not set (empty throwaway MySQL or MariaDB database).');
        }

        $connection = new ConnectionWrapper(new PdoConnection(
            $dsn,
            getenv('GOOGLESHOPPINGXML_TEST_USER') ?: null,
            getenv('GOOGLESHOPPINGXML_TEST_PASSWORD') ?: null,
        ));
        if ([] !== $connection->query("SHOW TABLES LIKE 'product'")->fetchAll()) {
            self::fail('The test database already has a product table: use an empty throwaway database.');
        }
        $this->connection = $connection;

        foreach (self::TABLES as $table => $columns) {
            $connection->exec(sprintf('CREATE TABLE `%s` (%s) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci', $table, $columns));
        }
        $connection->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');

        $connection->exec("INSERT INTO lang VALUES (1, 'fr_FR', 1)");
        $connection->exec('INSERT INTO currency VALUES (1, 1)');
        $connection->exec("INSERT INTO product VALUES (12, 'HELMET', 1, 1, NULL)");
        $connection->exec("INSERT INTO product_i18n VALUES (12, 'fr_FR', 'Casque', '', 'Casque jet')");
        $connection->exec("INSERT INTO product_sale_elements VALUES (120, 12, 'HELMET-M', 3, '', 1.2, 0)");
        $connection->exec('INSERT INTO product_price VALUES (120, 1, 199.0, 0, 0)');
        $connection->exec("INSERT INTO rewriting_url VALUES (1, 'old-helmet.html', 'product', '12', 'fr_FR', 2), (2, 'casque.html', 'product', '12', 'fr_FR', NULL)");
    }

    protected function tearDown(): void
    {
        if (null === $this->connection) {
            return;
        }
        foreach (array_keys(self::TABLES) as $table) {
            $this->connection->exec(sprintf('DROP TABLE IF EXISTS `%s`', $table));
        }
        $this->connection = null;
    }

    public function testTheRewrittenUrlIsReadWhenTheColumnsAndTheConnectionHaveDifferentCollations(): void
    {
        $collations = $this->connection?->query(
            "SELECT @@collation_connection, (SELECT COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rewriting_url' AND COLUMN_NAME = 'view_id')"
        )->fetch(\PDO::FETCH_NUM);
        self::assertSame(['utf8mb4_unicode_ci', 'utf8mb4_general_ci'], $collations);

        $rows = [];
        foreach ((new FeedRowReader($this->connection))->batches('fr_FR', 1, 1.0, []) as $batch) {
            array_push($rows, ...$batch);
        }

        self::assertSame(['casque.html'], array_map(static fn (FeedRow $row): ?string => $row->rewrittenUrl, $rows));
    }
}
