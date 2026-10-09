<?php

declare(strict_types=1);

namespace GoogleShoppingXml\Feed;

use GoogleShoppingXml\GoogleShoppingXml;

/**
 * What the merchant sets for the content of every feed (module configuration).
 *
 * - excludeOutOfStock: a combination without stock is left out, when the shop checks the stock;
 * - colorFeatureIds: the features read for <g:color> (at most three values, a "#rrggbb" code removed);
 * - genderFeatureIds, materialFeatureIds: the features read for <g:gender> and <g:material>;
 * - sizeAttributeIds: the attributes read for <g:size>; empty, no <g:size>;
 * - subtitleDetail: the product subtitle (chapo) sent as a <g:product_detail>;
 * - imageFilter: the image library filter set the image addresses are built with.
 */
final readonly class FeedSettings
{
    public const EAN_RULE_ALL = 'all';
    public const EAN_RULE_CHECK_FLEXIBLE = 'check_flexible';
    public const EAN_RULE_CHECK_STRICT = 'check_strict';
    public const EAN_RULE_NONE = 'none';

    /**
     * @param list<int> $colorFeatureIds
     * @param list<int> $genderFeatureIds
     * @param list<int> $materialFeatureIds
     * @param list<int> $sizeAttributeIds
     */
    public function __construct(
        public string $eanRule = self::EAN_RULE_CHECK_STRICT,
        public bool $excludeOutOfStock = false,
        public array $colorFeatureIds = [],
        public array $genderFeatureIds = [],
        public array $materialFeatureIds = [],
        public array $sizeAttributeIds = [],
        public bool $subtitleDetail = false,
        public string $imageFilter = GoogleShoppingXml::DEFAULT_IMAGE_FILTER,
    ) {
    }

    public static function fromConfiguration(): self
    {
        $eanRule = (string) GoogleShoppingXml::getConfigValue(GoogleShoppingXml::EAN_RULE, self::EAN_RULE_CHECK_STRICT);
        $imageFilter = trim((string) GoogleShoppingXml::getConfigValue(GoogleShoppingXml::IMAGE_FILTER, ''));

        return new self(
            eanRule: \in_array($eanRule, [self::EAN_RULE_ALL, self::EAN_RULE_CHECK_FLEXIBLE, self::EAN_RULE_CHECK_STRICT, self::EAN_RULE_NONE], true) ? $eanRule : self::EAN_RULE_CHECK_STRICT,
            excludeOutOfStock: '1' === (string) GoogleShoppingXml::getConfigValue(GoogleShoppingXml::EXCLUDE_OUT_OF_STOCK, ''),
            colorFeatureIds: self::parseIds((string) GoogleShoppingXml::getConfigValue(GoogleShoppingXml::FEATURE_COLOR_IDS, '')),
            genderFeatureIds: self::parseIds((string) GoogleShoppingXml::getConfigValue(GoogleShoppingXml::FEATURE_GENDER_IDS, '')),
            materialFeatureIds: self::parseIds((string) GoogleShoppingXml::getConfigValue(GoogleShoppingXml::FEATURE_MATERIAL_IDS, '')),
            sizeAttributeIds: self::parseIds((string) GoogleShoppingXml::getConfigValue(GoogleShoppingXml::ATTRIBUTE_SIZE_IDS, '')),
            subtitleDetail: '1' === (string) GoogleShoppingXml::getConfigValue(GoogleShoppingXml::SUBTITLE_DETAIL, ''),
            imageFilter: '' !== $imageFilter ? $imageFilter : GoogleShoppingXml::DEFAULT_IMAGE_FILTER,
        );
    }

    /**
     * @return list<int>
     */
    public static function parseIds(string $list): array
    {
        $ids = [];
        foreach (explode(',', $list) as $candidate) {
            $id = (int) trim($candidate);
            if ($id > 0 && !\in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * @return list<int> every feature the feed reads
     */
    public function featureIds(): array
    {
        return array_values(array_unique([...$this->colorFeatureIds, ...$this->genderFeatureIds, ...$this->materialFeatureIds]));
    }
}
