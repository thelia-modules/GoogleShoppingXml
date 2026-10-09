<?php

declare(strict_types=1);

namespace GoogleShoppingXml\Feed;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;

/**
 * Reads the combinations of the catalogue for a feed, by batches of combination ids: one query for the
 * combinations of a batch, one for their attribute values, one for the features of their products and
 * one for their images, whatever the size of the batch. Nothing is filtered here but the language and
 * the currency: the rules (visibility, exclusion, stock) belong to the generator.
 *
 * `rewriting_url.view_id` is a text column: it is compared with a text, or its index is not used (a
 * dependent subquery per combination over the whole table).
 *
 * Price on the feed currency: the price of that currency, or the price of the default currency times
 * the rate of the feed currency when the product follows the default currency (or has no price for it).
 */
final class FeedRowReader
{
    private const BATCH_SIZE = 1000;

    /**
     * The file of an image is translated: the one of the feed language, else the one of the default
     * language (one placeholder, the feed locale).
     */
    private const IMAGE_FILE = 'COALESCE('
        .'(SELECT NULLIF(own.file, \'\') FROM product_image_i18n AS own WHERE own.id = image.id AND own.locale = ?),'
        .'(SELECT NULLIF(fallback.file, \'\') FROM product_image_i18n AS fallback INNER JOIN lang ON lang.locale = fallback.locale AND lang.by_default = 1'
        .' WHERE fallback.id = image.id LIMIT 1))';

    public function __construct(private readonly ?ConnectionInterface $connection = null)
    {
    }

    /**
     * @param list<int> $featureIds the features to read for each product
     *
     * @return \Generator<list<FeedRow>>
     */
    public function batches(string $locale, int $currencyId, float $currencyRate, array $featureIds, ?int $limit = null): \Generator
    {
        $after = 0;
        $read = 0;

        while (true) {
            $size = null === $limit ? self::BATCH_SIZE : min(self::BATCH_SIZE, $limit - $read);
            if ($size <= 0) {
                return;
            }

            $rows = $this->combinations($locale, $currencyId, $currencyRate, $after, $size);
            if ([] === $rows) {
                return;
            }

            $read += \count($rows);
            $after = $rows[array_key_last($rows)]->productSaleElementsId;

            yield $this->withDetails($rows, $locale, $featureIds);
        }
    }

    /**
     * @return list<FeedRow>
     */
    private function combinations(string $locale, int $currencyId, float $currencyRate, int $after, int $size): array
    {
        $sql = 'SELECT pse.id, pse.ref, pse.product_id, product.ref AS product_ref, product.visible, pse.quantity, pse.ean_code, pse.weight, pse.promo,'
            .' product.tax_rule_id, product_i18n.title, product_i18n.chapo, product_i18n.description,'
            .' COALESCE(NULLIF(brand_i18n.title, \'\'), (SELECT any_brand.title FROM brand_i18n AS any_brand'
            .'   WHERE any_brand.id = product.brand_id AND any_brand.title <> \'\' ORDER BY any_brand.locale LIMIT 1)) AS brand,'
            .' (SELECT product_category.category_id FROM product_category'
            .'   WHERE product_category.product_id = product.id AND product_category.default_category = 1 LIMIT 1) AS default_category_id,'
            .' COALESCE(IF(price_on_currency.from_default_currency = 1, NULL, price_on_currency.price), ROUND(default_price.price * :rate, 2)) AS price,'
            .' COALESCE(IF(price_on_currency.from_default_currency = 1, NULL, price_on_currency.promo_price), ROUND(default_price.promo_price * :rate_promo, 2)) AS promo_price,'
            .' (SELECT rewriting_url.url FROM rewriting_url WHERE rewriting_url.view = \'product\' AND rewriting_url.view_id = CAST(product.id AS CHAR)'
            .'   AND rewriting_url.view_locale = :url_locale AND rewriting_url.redirected IS NULL ORDER BY rewriting_url.id DESC LIMIT 1) AS rewritten_url,'
            .' COALESCE(excluded.is_excluded, 0) AS is_excluded'
            .' FROM product_sale_elements AS pse'
            .' INNER JOIN product ON product.id = pse.product_id'
            .' LEFT JOIN product_i18n ON product_i18n.id = product.id AND product_i18n.locale = :locale'
            .' LEFT JOIN brand_i18n ON brand_i18n.id = product.brand_id AND brand_i18n.locale = :brand_locale'
            .' LEFT JOIN product_price AS price_on_currency ON price_on_currency.product_sale_elements_id = pse.id AND price_on_currency.currency_id = :currency'
            .' LEFT JOIN product_price AS default_price ON default_price.product_sale_elements_id = pse.id'
            .'   AND default_price.currency_id = (SELECT currency.id FROM currency WHERE currency.by_default = 1 ORDER BY currency.id LIMIT 1)'
            .' LEFT JOIN googleshoppingxml_product_excluded AS excluded ON excluded.pse_id = pse.id'
            .' WHERE pse.id > :after'
            .' ORDER BY pse.id'
            .' LIMIT '.$size;

        $statement = $this->connection()->prepare($sql);
        $statement->bindValue(':rate', (string) $currencyRate);
        $statement->bindValue(':rate_promo', (string) $currencyRate);
        $statement->bindValue(':url_locale', $locale);
        $statement->bindValue(':locale', $locale);
        $statement->bindValue(':brand_locale', $locale);
        $statement->bindValue(':currency', $currencyId, \PDO::PARAM_INT);
        $statement->bindValue(':after', $after, \PDO::PARAM_INT);
        $statement->execute();

        $rows = [];
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $rows[] = new FeedRow(
                productSaleElementsId: (int) $row['id'],
                reference: (string) $row['ref'],
                productId: (int) $row['product_id'],
                productReference: (string) $row['product_ref'],
                isVisible: 1 === (int) $row['visible'],
                isExcluded: 1 === (int) $row['is_excluded'],
                title: trim((string) $row['title']),
                subtitle: trim((string) $row['chapo']),
                description: (string) $row['description'],
                brand: trim((string) $row['brand']),
                quantity: (float) $row['quantity'],
                eanCode: trim((string) $row['ean_code']),
                weight: (float) $row['weight'],
                defaultCategoryId: null === $row['default_category_id'] ? null : (int) $row['default_category_id'],
                taxRuleId: (int) $row['tax_rule_id'],
                price: null === $row['price'] ? null : (float) $row['price'],
                promoPrice: null === $row['promo_price'] ? null : (float) $row['promo_price'],
                isPromo: 1 === (int) $row['promo'],
                rewrittenUrl: null === $row['rewritten_url'] || '' === $row['rewritten_url'] ? null : (string) $row['rewritten_url'],
            );
        }

        return $rows;
    }

    /**
     * @param list<FeedRow> $rows
     * @param list<int>     $featureIds
     *
     * @return list<FeedRow>
     */
    private function withDetails(array $rows, string $locale, array $featureIds): array
    {
        $combinationIds = array_map(static fn (FeedRow $row): int => $row->productSaleElementsId, $rows);
        $productIds = array_values(array_unique(array_map(static fn (FeedRow $row): int => $row->productId, $rows)));

        $attributeValues = $this->attributeValues($combinationIds, $locale);
        $featureValues = $this->featureValues($productIds, $featureIds, $locale);
        $productImages = $this->productImages($productIds, $locale);
        $combinationImages = $this->combinationImages($combinationIds, $locale);

        return array_map(static fn (FeedRow $row): FeedRow => $row->withDetails(
            $attributeValues[$row->productSaleElementsId] ?? [],
            $featureValues[$row->productId] ?? [],
            $combinationImages[$row->productSaleElementsId] ?? $productImages[$row->productId] ?? [],
        ), $rows);
    }

    /**
     * @param list<int> $combinationIds
     *
     * @return array<int, list<array{attribute_id: int, value_id: int, title: string}>>
     */
    private function attributeValues(array $combinationIds, string $locale): array
    {
        $values = [];
        foreach ($this->select(
            'SELECT combination.product_sale_elements_id, combination.attribute_id, combination.attribute_av_id, translation.title'
            .' FROM attribute_combination AS combination'
            .' INNER JOIN attribute_av_i18n AS translation ON translation.id = combination.attribute_av_id AND translation.locale = ?'
            .' WHERE combination.product_sale_elements_id IN ('.self::placeholders($combinationIds).')'
            .' ORDER BY combination.product_sale_elements_id, combination.attribute_id, combination.attribute_av_id',
            [$locale, ...$combinationIds],
        ) as $row) {
            $title = trim((string) $row['title']);
            if ('' === $title) {
                continue;
            }
            $values[(int) $row['product_sale_elements_id']][] = [
                'attribute_id' => (int) $row['attribute_id'],
                'value_id' => (int) $row['attribute_av_id'],
                'title' => $title,
            ];
        }

        return $values;
    }

    /**
     * @param list<int> $productIds
     * @param list<int> $featureIds
     *
     * @return array<int, array<int, list<string>>>
     */
    private function featureValues(array $productIds, array $featureIds, string $locale): array
    {
        if ([] === $featureIds) {
            return [];
        }

        $values = [];
        foreach ($this->select(
            'SELECT feature_product.product_id, feature_product.feature_id, translation.title'
            .' FROM feature_product'
            .' INNER JOIN feature_av_i18n AS translation ON translation.id = feature_product.feature_av_id AND translation.locale = ?'
            .' WHERE feature_product.product_id IN ('.self::placeholders($productIds).')'
            .' AND feature_product.feature_id IN ('.self::placeholders($featureIds).')'
            .' ORDER BY feature_product.product_id, feature_product.feature_id, feature_product.feature_av_id',
            [$locale, ...$productIds, ...$featureIds],
        ) as $row) {
            $title = trim((string) $row['title']);
            if ('' !== $title) {
                $values[(int) $row['product_id']][(int) $row['feature_id']][] = $title;
            }
        }

        return $values;
    }

    /**
     * @param list<int> $productIds
     *
     * @return array<int, list<string>>
     */
    private function productImages(array $productIds, string $locale): array
    {
        $files = [];
        foreach ($this->select(
            'SELECT image.product_id, '.self::IMAGE_FILE.' AS file FROM product_image AS image'
            .' WHERE image.visible = 1 AND image.product_id IN ('.self::placeholders($productIds).')'
            .' ORDER BY image.product_id, image.position, image.id',
            [$locale, ...$productIds],
        ) as $row) {
            if (null === $row['file'] || '' === $row['file']) {
                continue;
            }
            $files[(int) $row['product_id']][] = (string) $row['file'];
        }

        return $files;
    }

    /**
     * The images associated with a combination, which show the combination itself: they replace the
     * images of the product.
     *
     * @param list<int> $combinationIds
     *
     * @return array<int, list<string>>
     */
    private function combinationImages(array $combinationIds, string $locale): array
    {
        $files = [];
        foreach ($this->select(
            'SELECT link.product_sale_elements_id, '.self::IMAGE_FILE.' AS file FROM product_sale_elements_product_image AS link'
            .' INNER JOIN product_image AS image ON image.id = link.product_image_id AND image.visible = 1'
            .' WHERE link.product_sale_elements_id IN ('.self::placeholders($combinationIds).')'
            .' ORDER BY link.product_sale_elements_id, image.position, image.id',
            [$locale, ...$combinationIds],
        ) as $row) {
            if (null === $row['file'] || '' === $row['file']) {
                continue;
            }
            $files[(int) $row['product_sale_elements_id']][] = (string) $row['file'];
        }

        return $files;
    }

    /**
     * @param list<int|string> $values
     */
    private static function placeholders(array $values): string
    {
        return implode(',', array_fill(0, \count($values), '?'));
    }

    /**
     * @param list<int|string> $parameters
     *
     * @return list<array<string, mixed>>
     */
    private function select(string $sql, array $parameters): array
    {
        $statement = $this->connection()->prepare($sql);
        foreach ($parameters as $index => $parameter) {
            $statement->bindValue($index + 1, $parameter, \is_int($parameter) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $statement->execute();

        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function connection(): ConnectionInterface
    {
        return $this->connection ?? Propel::getConnection();
    }
}
