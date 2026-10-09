<?php

declare(strict_types=1);

namespace GoogleShoppingXml\Feed;

use GoogleShoppingXml\Controller\GoogleFieldAssociationController;
use GoogleShoppingXml\Event\FeedItemEvent;
use GoogleShoppingXml\Event\GoogleShoppingXmlEvents;
use GoogleShoppingXml\Events\AdditionalFieldEvent;
use GoogleShoppingXml\Exception\FeedGenerationException;
use GoogleShoppingXml\GoogleShoppingXml;
use GoogleShoppingXml\Model\GoogleshoppingxmlFeed;
use GoogleShoppingXml\Model\GoogleshoppingxmlGoogleFieldAssociationQuery;
use GoogleShoppingXml\Model\GoogleshoppingxmlLogQuery;
use GoogleShoppingXml\Tools\GtinChecker;
use Propel\Runtime\Propel;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Domain\Taxation\TaxEngine\TaxCalculatorFactoryInterface;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Tools\URL;

/**
 * Writes the XML file of a feed: one <item> per combination of a visible product, in the language,
 * currency and country of the feed, with the links on the domain of its language.
 *
 * Rules, in this order: a combination of a product offline, excluded in the back office, or out of stock
 * when the module leaves those out, is skipped (and counted in the log); listeners of FeedItemEvent can
 * then leave an item out or change its fields; an item missing a required field (title, description,
 * link, image, price, brand) or with an EAN refused by the EAN rule is logged as an error and skipped,
 * the generation goes on.
 *
 * The file is written next to its final name and renamed once complete: the address of the feed never
 * serves a half-written file, and a failed generation leaves the previous feed in place. A feed with no
 * item at all is a failure too.
 */
final class FeedGenerator
{
    public const FILES_DIRECTORY = THELIA_LOCAL_DIR.'GoogleShoppingXML'.\DIRECTORY_SEPARATOR;

    private const MAX_COLORS = 3;

    public function __construct(
        private readonly FeedRowReader $rowReader,
        private readonly ImageUrls $imageUrls,
        private readonly TaxCalculatorFactoryInterface $taxCalculatorFactory,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly TranslatorInterface $translator,
        #[Autowire(service: 'service_container')]
        private readonly ContainerInterface $container,
    ) {
    }

    public static function pathOf(GoogleshoppingxmlFeed $feed): string
    {
        return self::FILES_DIRECTORY.$feed->getLabel().'.xml';
    }

    /**
     * Takes the lock of the generation, so that two runs (overlapping scheduled tasks) never write the
     * same files at once. Null when another run holds it; released when the handle is closed.
     *
     * @return resource|null
     */
    public static function tryLock()
    {
        self::ensureDirectory();
        $handle = fopen(self::FILES_DIRECTORY.'.generate.lock', 'c');
        if (false === $handle) {
            throw FeedGenerationException::notWritable(self::FILES_DIRECTORY);
        }
        if (!flock($handle, \LOCK_EX | \LOCK_NB)) {
            fclose($handle);

            return null;
        }

        return $handle;
    }

    /**
     * @return int the number of items written
     *
     * @throws FeedGenerationException when the file is not replaced
     */
    public function generate(GoogleshoppingxmlFeed $feed, ?FeedSettings $settings = null, ?int $limit = null): int
    {
        $logger = GoogleshoppingxmlLogQuery::create();

        try {
            return $this->write($feed, $settings ?? FeedSettings::fromConfiguration(), $limit, $logger);
        } catch (\Throwable $failure) {
            $logger->logFatal($feed, null, $failure->getMessage(), $failure instanceof FeedGenerationException ? null : $failure->getFile().' at line '.$failure->getLine());

            throw $failure instanceof FeedGenerationException ? $failure : new FeedGenerationException($failure->getMessage(), 0, $failure);
        }
    }

    private function write(GoogleshoppingxmlFeed $feed, FeedSettings $settings, ?int $limit, GoogleshoppingxmlLogQuery $logger): int
    {
        $lang = $feed->getLang();
        $currency = $feed->getCurrency();
        $country = $feed->getCountry();
        $locale = (string) $lang->getLocale();

        $this->imageUrls->assertFilterSetExists($settings->imageFilter);
        $baseUrl = $this->baseUrl((string) $lang->getUrl(), $locale);
        $storeName = $this->storeSetting('store_name', $locale);
        $storeDescription = $this->storeSetting('store_description', $locale);

        $taxCountry = ConfigQuery::read('google_shopping_use_default_country_tax') ? Country::getDefaultCountry() : $country;
        $priceFormatter = new PriceFormatter($lang, $currency);
        $context = [
            'feed' => $feed,
            'locale' => $locale,
            'base_url' => $baseUrl,
            'settings' => $settings,
            'check_stock' => ConfigQuery::checkAvailableStock(),
            'prices' => $priceFormatter,
            'taxes' => new TaxedPrices($this->taxCalculatorFactory, $taxCountry),
            'shipping' => new Shipping($this->container, $country, $locale, (float) $currency->getRate(), $priceFormatter),
            'categories' => new Categories($locale, (int) $lang->getId()),
            'associations' => $this->fieldAssociations(),
            'logger' => $logger,
        ];

        self::ensureDirectory();
        $path = self::pathOf($feed);
        $temporaryPath = \dirname($path).\DIRECTORY_SEPARATOR.'.'.basename($path).'.tmp';
        $handle = fopen($temporaryPath, 'w');
        if (false === $handle) {
            throw FeedGenerationException::notWritable($temporaryPath);
        }

        $counts = ['written' => 0, 'invisible' => 0, 'excluded' => 0, 'out_of_stock' => 0, 'left_out' => 0, 'errors' => 0];

        try {
            $this->put($handle, '<?xml version="1.0"?>'.\PHP_EOL.'<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">'.\PHP_EOL.'<channel>'.\PHP_EOL
                .'<title>'.self::text($storeName).'</title>'.\PHP_EOL
                .'<link>'.self::text($baseUrl).'</link>'.\PHP_EOL
                .'<description>'.self::text($storeDescription).'</description>'.\PHP_EOL);

            $featureIds = array_values(array_unique([...$settings->featureIds(), ...$context['associations']['feature_ids']]));
            foreach ($this->rowReader->batches($locale, (int) $currency->getId(), (float) $currency->getRate(), $featureIds, $limit) as $rows) {
                foreach ($rows as $row) {
                    $outcome = $this->item($row, $context);
                    ++$counts[$outcome[0]];
                    if (null !== $outcome[1]) {
                        $this->put($handle, $outcome[1]);
                    }
                }
            }

            $this->put($handle, '</channel>'.\PHP_EOL.'</rss>');
        } catch (\Throwable $failure) {
            fclose($handle);
            @unlink($temporaryPath);

            throw $failure;
        }

        fclose($handle);

        $this->logCounts($feed, $counts, $logger);

        if (0 === $counts['written']) {
            @unlink($temporaryPath);

            throw FeedGenerationException::emptyFeed((string) $feed->getLabel());
        }

        if (!rename($temporaryPath, $path)) {
            @unlink($temporaryPath);

            throw FeedGenerationException::notWritable($path);
        }

        $logger->logSuccess($feed, null, $this->translator->trans('The XML file has been successfully generated with %nb product items.', ['%nb' => $counts['written']], GoogleShoppingXml::DOMAIN_NAME));

        return $counts['written'];
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array{0: string, 1: ?string} the counter to raise and the XML of the item, if written
     */
    private function item(FeedRow $row, array $context): array
    {
        /** @var FeedSettings $settings */
        $settings = $context['settings'];
        /** @var GoogleshoppingxmlFeed $feed */
        $feed = $context['feed'];
        /** @var GoogleshoppingxmlLogQuery $logger */
        $logger = $context['logger'];

        if (!$row->isVisible) {
            return ['invisible', null];
        }
        if ($row->isExcluded) {
            return ['excluded', null];
        }
        if ($context['check_stock'] && $row->quantity <= 0 && $settings->excludeOutOfStock) {
            return ['out_of_stock', null];
        }

        $productUrl = $this->productUrl($row, $context['locale'], $context['base_url']);
        [$item, $eanError] = $this->fields($row, $productUrl, $context);

        $additionalFieldEvent = new AdditionalFieldEvent($row->productSaleElementsId);
        $this->dispatcher->dispatch($additionalFieldEvent, AdditionalFieldEvent::ADD_FIELD_EVENT);
        foreach ($additionalFieldEvent->getFields() as $name => $value) {
            $item->set((string) $name, (string) $value);
        }

        $event = new FeedItemEvent($feed, $context['locale'], $row, $item, $productUrl);
        $this->dispatcher->dispatch($event, GoogleShoppingXmlEvents::FEED_ITEM);
        if ($event->isExcluded()) {
            if (null !== $event->getExclusionReason()) {
                $logger->logInfo($feed, $row->productSaleElementsId, $event->getExclusionReason());
            }

            return ['left_out', null];
        }

        $error = $this->firstError($item, $row, $eanError, $context);
        if (null !== $error) {
            $logger->logError($feed, $row->productSaleElementsId, $error[0], $error[1]);

            return ['errors', null];
        }

        if (!$item->has('google_product_category')) {
            $logger->logWarning(
                $feed,
                $row->productSaleElementsId,
                $this->translator->trans('No Google category related to the Thelia category "%cat".', ['%cat' => (string) $item->get('product_type')], GoogleShoppingXml::DOMAIN_NAME),
                $this->translator->trans('This product s category is not related to any Google category. It is required by Google for most products. Please add one in the [Google Taxonomy] tab.', [], GoogleShoppingXml::DOMAIN_NAME),
            );
        }

        return ['written', self::xml($item)];
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array{0: FeedItem, 1: ?string} the fields, and the EAN refused by the strict rule
     */
    private function fields(FeedRow $row, string $productUrl, array $context): array
    {
        /** @var FeedSettings $settings */
        $settings = $context['settings'];
        /** @var PriceFormatter $prices */
        $prices = $context['prices'];
        /** @var TaxedPrices $taxes */
        $taxes = $context['taxes'];
        /** @var Categories $categories */
        $categories = $context['categories'];
        $locale = $context['locale'];
        $associations = $this->associatedValues($row, $context['associations']);

        $item = new FeedItem();
        $item->set('id', (string) $row->productSaleElementsId);

        $attributeTitles = array_map(static fn (array $value): string => $value['title'], $row->attributeValues);
        $title = $row->title;
        if ('' !== $title && [] !== $attributeTitles) {
            $title .= ' - '.implode(' - ', $attributeTitles);
        }
        $item->set('title', $title);

        $subtitle = self::plainText($row->subtitle);
        if ($settings->subtitleDetail && '' !== $subtitle) {
            $item->set('product_detail', [[
                'section_name' => $this->translator->trans('General', [], GoogleShoppingXml::DOMAIN_NAME, $locale),
                'attribute_name' => $this->translator->trans('Subtitle', [], GoogleShoppingXml::DOMAIN_NAME, $locale),
                'attribute_value' => $subtitle,
            ]]);
        }

        $item->set('description', self::plainText($row->description));
        $item->set('link', '' === $productUrl || '' === $row->reference
            ? $productUrl
            : $productUrl.(str_contains($productUrl, '?') ? '&' : '?').'ref='.rawurlencode($row->reference));

        $images = [];
        foreach ($row->imageFiles as $file) {
            $path = $this->imageUrls->pathOf($file, $settings->imageFilter);
            if (null !== $path) {
                $images[] = $context['base_url'].$path;
            }
        }
        if ([] !== $images) {
            $item->set('image_link', array_shift($images));
        }
        if ([] !== $images) {
            $item->set('additional_image_link', $images);
        }

        $item->set('availability', $context['check_stock'] && $row->quantity <= 0 ? 'out_of_stock' : 'in_stock');

        if (null !== $row->price && $row->price > 0) {
            $taxedPrice = $taxes->taxedPrice($row->price, $row->taxRuleId, $row->productId);
            $item->set('price', $prices->format($taxedPrice));

            if ($row->isPromo && null !== $row->promoPrice && $row->promoPrice > 0) {
                $taxedPromoPrice = $taxes->taxedPrice($row->promoPrice, $row->taxRuleId, $row->productId);
                if ($taxedPromoPrice < $taxedPrice) {
                    $item->set('sale_price', $prices->format($taxedPromoPrice));
                }
            }
        }

        if ('' !== $row->brand && !isset($associations['brand'])) {
            $item->set('brand', $row->brand);
        }

        $eanError = null;
        $gtin = $this->gtin($row->eanCode, $settings->eanRule);
        if (false === $gtin) {
            $eanError = $row->eanCode;
        } elseif (null !== $gtin) {
            $item->set('gtin', $gtin);
        }
        $item->set('identifier_exists', \is_string($gtin) ? 'yes' : 'no');
        $item->set('item_group_id', $row->productReference);

        $shipping = $context['shipping']->of($row->weight, (float) $row->price);
        if ([] !== $shipping) {
            $item->set('shipping', $shipping);
        }

        $googleCategory = $categories->googleCategoryOf($row->defaultCategoryId);
        if (null !== $googleCategory) {
            $item->set('google_product_category', $googleCategory);
        }
        $path = $categories->pathOf($row->defaultCategoryId);
        if (null !== $path && '' !== $path) {
            $item->set('product_type', $path);
        }

        $size = $this->size($row, $settings);
        if ('' !== $size) {
            $item->set('size', $size);
        }
        foreach (['gender' => $settings->genderFeatureIds, 'material' => $settings->materialFeatureIds] as $name => $featureIds) {
            $value = $this->firstFeatureValue($row, $featureIds);
            if (null !== $value) {
                $item->set($name, $value);
            }
        }
        $color = $this->colors($row, $settings);
        if ('' !== $color) {
            $item->set('color', $color);
        }

        if (!isset($associations['condition'])) {
            $item->set('condition', 'new');
        }

        foreach ($associations as $name => $value) {
            $item->set($name, $value);
        }

        return [$item, $eanError];
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array{0: string, 1: ?string}|null the message and the help of the first missing information
     */
    private function firstError(FeedItem $item, FeedRow $row, ?string $eanError, array $context): ?array
    {
        /** @var GoogleshoppingxmlFeed $feed */
        $feed = $context['feed'];
        $langTitle = (string) $feed->getLang()->getTitle();
        $domain = GoogleShoppingXml::DOMAIN_NAME;

        return match (true) {
            '' === (string) $item->get('title') || '' === $row->title => [
                $this->translator->trans('Missing product title for the language "%lang"', ['%lang' => $langTitle], $domain),
                $this->translator->trans('Check that this product has a valid title in this langage.', [], $domain),
            ],
            '' === (string) $item->get('description') => [
                $this->translator->trans('Missing product description for the language "%lang"', ['%lang' => $langTitle], $domain),
                $this->translator->trans('Check that this product has a valid description in this langage.', [], $domain),
            ],
            '' === (string) $item->get('link') => [$this->translator->trans('Missing product URL', [], $domain), null],
            !$item->has('image_link') => [
                $this->translator->trans('Missing product image', [], $domain),
                $this->translator->trans('Please add an image for this product.', [], $domain),
            ],
            !$item->has('price') => [
                $this->translator->trans('Missing product price for the currency "%code"', ['%code' => (string) $feed->getCurrency()->getCode()], $domain),
                $this->translator->trans('Unable to compute a price for this product and this currency. Specify one manually or check [Apply exchange rates] in the Edit Product page for this currency.', [], $domain),
            ],
            '' === (string) $item->get('brand') => [
                $this->translator->trans('Missing product brand for the language "%lang"', ['%lang' => $langTitle], $domain),
                $this->translator->trans('The product has no brand or the brand doesn t have a title in this language. If none of your product has a brand, please add a [brand] field with a fixed value in the [Advanded Configuration] tab as this field is required by Google.', [], $domain),
            ],
            null !== $eanError => [
                $this->translator->trans('Invalid GTIN/EAN code : "%code"', ['%code' => $eanError], $domain),
                $this->translator->trans('The product s identification code seems invalid. You can set a valid EAN code in the Edit product page or disable the verification in the [Advanced configuration] tab.', [], $domain),
            ],
            default => null,
        };
    }

    /**
     * @return string|false|null the GTIN to send, false when the strict rule refuses the code, null when none is sent
     */
    private function gtin(string $ean, string $rule): string|false|null
    {
        if ('' === $ean || FeedSettings::EAN_RULE_NONE === $rule) {
            return null;
        }
        if (FeedSettings::EAN_RULE_ALL === $rule || (new GtinChecker())->isValidGtin($ean)) {
            return $ean;
        }

        return FeedSettings::EAN_RULE_CHECK_STRICT === $rule ? false : null;
    }

    private function size(FeedRow $row, FeedSettings $settings): string
    {
        $titles = [];
        foreach ($row->attributeValues as $value) {
            if ([] === $settings->sizeAttributeIds || \in_array($value['attribute_id'], $settings->sizeAttributeIds, true)) {
                $titles[] = $value['title'];
            }
        }

        return implode(' - ', $titles);
    }

    /**
     * @param list<int> $featureIds
     */
    private function firstFeatureValue(FeedRow $row, array $featureIds): ?string
    {
        foreach ($featureIds as $featureId) {
            foreach ($row->featureValues[$featureId] ?? [] as $value) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Up to three colors, in the order of the features set, without the "#rrggbb" code some shops put
     * after the name ("Brown/#8B4513"): Google wants names.
     */
    private function colors(FeedRow $row, FeedSettings $settings): string
    {
        $colors = [];
        foreach ($settings->colorFeatureIds as $featureId) {
            foreach ($row->featureValues[$featureId] ?? [] as $value) {
                $name = trim(rtrim((string) preg_replace('/#[0-9A-Fa-f]+/', '', $value), '/'));
                if ('' !== $name && !\in_array($name, $colors, true)) {
                    $colors[] = $name;
                }
                if (\count($colors) >= self::MAX_COLORS) {
                    break 2;
                }
            }
        }

        return implode('/', $colors);
    }

    /**
     * @return array{fixed: array<string, string>, attributes: array<string, int>, features: array<string, int>, feature_ids: list<int>}
     */
    private function fieldAssociations(): array
    {
        $associations = ['fixed' => [], 'attributes' => [], 'features' => [], 'feature_ids' => []];
        foreach (GoogleshoppingxmlGoogleFieldAssociationQuery::create()->find() as $association) {
            $name = (string) $association->getGoogleField();
            if (1 !== preg_match('/^[a-z][a-z0-9_]*$/', $name) || \in_array($name, GoogleFieldAssociationController::FIELDS_NATIVELY_DEFINED, true)) {
                continue;
            }

            match ((int) $association->getAssociationType()) {
                GoogleFieldAssociationController::ASSO_TYPE_FIXED_VALUE => $associations['fixed'][$name] = (string) $association->getFixedValue(),
                GoogleFieldAssociationController::ASSO_TYPE_RELATED_TO_THELIA_ATTRIBUTE => $associations['attributes'][$name] = (int) $association->getIdRelatedAttribute(),
                GoogleFieldAssociationController::ASSO_TYPE_RELATED_TO_THELIA_FEATURE => $associations['features'][$name] = (int) $association->getIdRelatedFeature(),
                default => null,
            };
        }
        $associations['feature_ids'] = array_values(array_unique(array_values($associations['features'])));

        return $associations;
    }

    /**
     * The fields the merchant associates with a fixed value, an attribute or a feature, when the
     * combination has a value for them.
     *
     * @param array{fixed: array<string, string>, attributes: array<string, int>, features: array<string, int>, feature_ids: list<int>} $associations
     *
     * @return array<string, string>
     */
    private function associatedValues(FeedRow $row, array $associations): array
    {
        $values = $associations['fixed'];
        foreach ($associations['attributes'] as $name => $attributeId) {
            $titles = array_map(
                static fn (array $value): string => $value['title'],
                array_filter($row->attributeValues, static fn (array $value): bool => $value['attribute_id'] === $attributeId),
            );
            if ([] !== $titles) {
                $values[$name] = implode('/', $titles);
            }
        }
        foreach ($associations['features'] as $name => $featureId) {
            if ([] !== ($row->featureValues[$featureId] ?? [])) {
                $values[$name] = implode('/', $row->featureValues[$featureId]);
            }
        }

        return $values;
    }

    private function productUrl(FeedRow $row, string $locale, string $baseUrl): string
    {
        $url = $row->rewrittenUrl;
        if (null === $url) {
            $retrieved = URL::getInstance()->retrieve('product', $row->productId, $locale);
            $url = !empty($retrieved->rewrittenUrl) ? $retrieved->rewrittenUrl : $retrieved->url;
            if (!\is_string($url) || '' === $url) {
                return '';
            }
        }

        $parts = parse_url($url);
        if (!\is_array($parts) || !isset($parts['path'])) {
            return '';
        }

        return $baseUrl.'/'.ltrim($parts['path'], '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    /**
     * The address of the language when each language has its domain, else the address of the shop.
     */
    private function baseUrl(string $langUrl, string $locale): string
    {
        $url = rtrim(ConfigQuery::isMultiDomainActivated() ? $langUrl : (string) ConfigQuery::getConfiguredShopUrl(), '/');
        if ('' === $url) {
            throw FeedGenerationException::noShopUrl($locale);
        }

        return $url;
    }

    /**
     * A store setting in the language of the feed ("store_name_de_DE"), else the one of the shop.
     */
    private function storeSetting(string $name, string $locale): string
    {
        $statement = Propel::getConnection()->prepare('SELECT name, value FROM config WHERE name IN (?, ?)');
        $statement->bindValue(1, $name.'_'.$locale);
        $statement->bindValue(2, $name);
        $statement->execute();
        $values = $statement->fetchAll(\PDO::FETCH_KEY_PAIR);

        $value = trim((string) ($values[$name.'_'.$locale] ?? $values[$name] ?? ''));
        if ('' === $value) {
            throw FeedGenerationException::missingStoreSetting($name, $locale);
        }

        return $value;
    }

    /**
     * @param array<string, int> $counts
     */
    private function logCounts(GoogleshoppingxmlFeed $feed, array $counts, GoogleshoppingxmlLogQuery $logger): void
    {
        $domain = GoogleShoppingXml::DOMAIN_NAME;
        $messages = [
            'invisible' => ['%nb product item(s) have been skipped because they were set as not visible.', 'You can set your product s visibility in the product edit tool by checking the box [This product is online].'],
            'excluded' => ['%nb product item(s) have been skipped because they are excluded from Google Shopping.', 'The exclusion is set for each combination in the Modules tab of the product.'],
            'out_of_stock' => ['%nb product item(s) have been skipped because they are out of stock.', 'You can change this behavior in the module configuration.'],
            'left_out' => ['%nb product item(s) have been left out by the rules of the shop.', null],
            'errors' => ['%nb product item(s) have been skipped because of errors.', 'Check the ERROR messages below to get further details about the error.'],
        ];

        foreach ($messages as $counter => [$message, $help]) {
            if ($counts[$counter] > 0) {
                $logger->logInfo($feed, null, $this->translator->trans($message, ['%nb' => $counts[$counter]], $domain), null === $help ? null : $this->translator->trans($help, [], $domain));
            }
        }
    }

    public static function xml(FeedItem $item): string
    {
        $xml = '<item>'.\PHP_EOL;
        foreach ($item->all() as $name => $value) {
            if (\is_string($value)) {
                $xml .= self::element($name, $value);
                continue;
            }
            foreach ($value as $entry) {
                if (\is_string($entry)) {
                    $xml .= self::element($name, $entry);
                    continue;
                }
                $xml .= '<g:'.$name.'>'.\PHP_EOL;
                foreach ($entry as $childName => $childValue) {
                    $xml .= self::element((string) $childName, (string) $childValue);
                }
                $xml .= '</g:'.$name.'>'.\PHP_EOL;
            }
        }

        return $xml.'</item>'.\PHP_EOL;
    }

    private static function element(string $name, string $value): string
    {
        return '' === $value ? '' : '<g:'.$name.'>'.self::text($value).'</g:'.$name.'>'.\PHP_EOL;
    }

    /**
     * Text escaped for XML, without the characters XML 1.0 forbids (a control character pasted in a
     * description would make the whole file unreadable).
     */
    private static function text(string $value): string
    {
        $value = (string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value);

        return htmlspecialchars($value, \ENT_XML1, 'UTF-8');
    }

    private static function plainText(string $html): string
    {
        return html_entity_decode(trim(strip_tags($html)), \ENT_QUOTES | \ENT_SUBSTITUTE | \ENT_HTML401, 'UTF-8');
    }

    /**
     * @param resource $handle
     */
    private function put($handle, string $content): void
    {
        if (false === fwrite($handle, $content)) {
            throw FeedGenerationException::notWritable(self::FILES_DIRECTORY);
        }
    }

    private static function ensureDirectory(): void
    {
        if (!is_dir(self::FILES_DIRECTORY) && !@mkdir(self::FILES_DIRECTORY, 0o755, true) && !is_dir(self::FILES_DIRECTORY)) {
            throw FeedGenerationException::notWritable(self::FILES_DIRECTORY);
        }
    }
}
