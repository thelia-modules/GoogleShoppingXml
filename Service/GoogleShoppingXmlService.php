<?php

namespace GoogleShoppingXml\Service;

use GoogleShoppingXml\Controller\GoogleFieldAssociationController;
use GoogleShoppingXml\Events\AdditionalFieldEvent;
use GoogleShoppingXml\GoogleShoppingXml;
use GoogleShoppingXml\Model\GoogleshoppingxmlFeed;
use GoogleShoppingXml\Model\GoogleshoppingxmlFeedQuery;
use GoogleShoppingXml\Model\GoogleshoppingxmlGoogleFieldAssociation;
use GoogleShoppingXml\Model\GoogleshoppingxmlGoogleFieldAssociationQuery;
use GoogleShoppingXml\Model\GoogleshoppingxmlIgnoreCategoryQuery;
use GoogleShoppingXml\Model\GoogleshoppingxmlLogQuery;
use GoogleShoppingXml\Tools\GtinChecker;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Propel;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Thelia\Action\Image;
use Thelia\Core\Event\Image\ImageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Translation\Translator;
use Thelia\Log\Tlog;
use Thelia\Model\AreaDeliveryModuleQuery;
use Thelia\Model\Base\BrandI18nQuery;
use Thelia\Model\Base\ProductCategoryQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderPostage;
use Thelia\Model\ProductImageQuery;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\RewritingUrlQuery;
use Thelia\Model\TaxRule;
use Thelia\Model\TaxRuleQuery;
use Thelia\Module\BaseModule;
use Thelia\TaxEngine\Calculator;
use Thelia\Tools\MoneyFormat;
use Thelia\Tools\URL;

class GoogleShoppingXmlService
{
    /**
     * @var GoogleshoppingxmlLogQuery $logger
     */
    private $logger;

    private $ean_rule;

    private $nb_pse;
    private $nb_pse_invisible;
    private $nb_pse_error;

    private $container;
    private $eventDispatcher;
    private $request;

    private $googleCategories;
    private $theliaCategories;

    const EAN_RULE_ALL = "all";
    const EAN_RULE_CHECK_FLEXIBLE = "check_flexible";
    const EAN_RULE_CHECK_STRICT = "check_strict";
    const EAN_RULE_NONE = "none";

    const XML_FILES_DIR = THELIA_LOCAL_DIR . 'GoogleShoppingXML' . DS;

    const DEFAULT_EAN_RULE = self::EAN_RULE_CHECK_STRICT;

    public function __construct(ContainerInterface $container, EventDispatcherInterface $eventDispatcher, Request $request)
    {
        $this->container = $container;
        $this->eventDispatcher = $eventDispatcher;
        $this->request = $request;
    }

    public function getFeedXmlAction($feedId, $limit = null, $offset = null)
    {
        $this->logger = GoogleshoppingxmlLogQuery::create();
        $this->ean_rule = GoogleShoppingXml::getConfigValue("ean_rule", self::DEFAULT_EAN_RULE);
        Tlog::getInstance()->info('GoogleShoppingXML start generating XML');

        $feed = GoogleshoppingxmlFeedQuery::create()->findOneById($feedId);

        if ($feed == null) {
            return null;
        }

        try {
            $shippingArray = $this->buildShippingArray($feed);

            $pseArray = [];
            $stmt = $this->getProductItems($feed, $limit, $offset);
            $this->getGoogleAndTheliaCategories($feed);
            $urlManager = URL::getInstance();
            $taxCalculatorsArray = [];
            $fieldAssociationCollection = $this->getFieldAssociationCollection($feed);



            $i = 0;
            while ($pse = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $timestamp_debut = microtime(true);
                $this->injectGoogleCategories($pse, $feed);
                $this->injectUrls($pse, $feed, $urlManager);
                $this->injectTaxedPrices($pse, $taxCalculatorsArray, $feed, $this->getTaxedRules());
                $this->injectCustomAssociationFields($pse, $feed, $fieldAssociationCollection);
                $this->injectImages($pse);
                $this->injectBrand($pse, $feed);
                $this->injectAttributesInTitle($pse, $feed);
                $pseArray[] = $pse;
                $i++;
            }

            $this->nb_pse = 0;
            $this->nb_pse_invisible = 0;
            $this->nb_pse_error = 0;
            if (null === $this->request->getSession()) {
                $session = new Session(new MockArraySessionStorage());
                $session
                    ->setLang($feed->getLang())
                    ->setCurrency($feed->getCurrency());

                $this->request->setSession($session);
            }
            $content = $this->renderXmlAll($feed, $pseArray, $shippingArray);

            if ($this->nb_pse_invisible > 0) {
                $this->logger->logInfo(
                    $feed,
                    null,
                    Translator::getInstance()->trans('%nb product item(s) have been skipped because they were set as not visible.', ['%nb' => $this->nb_pse_invisible], GoogleShoppingXml::DOMAIN_NAME),
                    Translator::getInstance()->trans('You can set your product s visibility in the product edit tool by checking the box [This product is online].', [], GoogleShoppingXml::DOMAIN_NAME)
                );
            }

            if ($this->nb_pse_error > 0) {
                $this->logger->logInfo(
                    $feed,
                    null,
                    Translator::getInstance()->trans('%nb product item(s) have been skipped because of errors.', ['%nb' => $this->nb_pse_error], GoogleShoppingXml::DOMAIN_NAME),
                    Translator::getInstance()->trans('Check the ERROR messages below to get further details about the error.', [], GoogleShoppingXml::DOMAIN_NAME)
                );
            }

            if ($this->nb_pse <= 0) {
                $this->logger->logFatal(
                    $feed,
                    null,
                    Translator::getInstance()->trans('No product in the feed', [], GoogleShoppingXml::DOMAIN_NAME),
                    Translator::getInstance()->trans('Your products may not have been included in the feed due to errors. Check the others messages in this log.', [], GoogleShoppingXml::DOMAIN_NAME)
                );
            } else {
                $nb_line_xml = substr_count($content, PHP_EOL);
                if ($nb_line_xml <= 8) {
                    $this->logger->logFatal(
                        $feed,
                        null,
                        Translator::getInstance()->trans('Empty generated XML file', [], GoogleShoppingXml::DOMAIN_NAME),
                        Translator::getInstance()->trans('Your products may not have been included in the feed due to errors. Check the others messages in this log.', [], GoogleShoppingXml::DOMAIN_NAME)
                    );
                } else {
                    $this->logger->logSuccess($feed, null, Translator::getInstance()->trans('The XML file has been successfully generated with %nb product items.', ['%nb' => $this->nb_pse], GoogleShoppingXml::DOMAIN_NAME));
                }
            }
            Tlog::getInstance()->info('GoogleShoppingXML stop generating XML ,' . count($pseArray) . ' products');

            return $content;

        } catch (\Exception $ex) {
            $this->logger->logFatal($feed, null, $ex->getMessage(), $ex->getFile() . " at line " . $ex->getLine());
            Tlog::getInstance()->error('GoogleShoppingXML : ' . $ex->getMessage());
            throw $ex;
        }
    }

    protected function renderXmlAll($feed, &$pseArray, $shippingArray)
    {
        $checkAvailability = ConfigQuery::checkAvailableStock();

        $str = '<?xml version="1.0"?>' . PHP_EOL;
        $str .= '<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">' . PHP_EOL;
        $str .= '<channel>' . PHP_EOL;

        $store_name = ConfigQuery::getStoreName();
        $store_description = ConfigQuery::getStoreDescription();

        if (empty($store_name)) {
            $this->logger->logError(
                $feed,
                null,
                Translator::getInstance()->trans('Missing store name', [], GoogleShoppingXml::DOMAIN_NAME),
                Translator::getInstance()->trans('You must set the store name in Configuration > Store', [], GoogleShoppingXml::DOMAIN_NAME)
            );
            throw new \Exception('Fatal error during GoogleShopping XML generation : the store name is missing.');
        }

        if (empty($store_description)) {
            $this->logger->logError(
                $feed,
                null,
                Translator::getInstance()->trans('Missing store description', [], GoogleShoppingXml::DOMAIN_NAME),
                Translator::getInstance()->trans('You must set the store description in Configuration > Store', [], GoogleShoppingXml::DOMAIN_NAME)
            );
            throw new \Exception('Fatal error during GoogleShopping XML generation : the store description is missing.');
        }

        $str .= '<title>' . $this->xmlSafeEncode($store_name) . '</title>' . PHP_EOL;
        $str .= '<link>' . $this->xmlSafeEncode(URL::getInstance()->getIndexPage()) . '</link>' . PHP_EOL;
        $str .= '<description>' . $this->xmlSafeEncode($store_description) . '</description>' . PHP_EOL;

        $shippingStr = '';
        foreach ($shippingArray as $shipping) {
            $shippingStr .= '<g:shipping>' . PHP_EOL;
            $shippingStr .= '<g:country>' . $shipping['country_code'] . '</g:country>' . PHP_EOL;
            $shippingStr .= '<g:service>' . $shipping['service'] . '</g:service>' . PHP_EOL;
            $formattedPrice = MoneyFormat::getInstance($this->request)->formatByCurrency($shipping['price'], null, null, null, $shipping['currency_id']);
            $shippingStr .= '<g:price>' . $formattedPrice . '</g:price>' . PHP_EOL;
            $shippingStr .= '</g:shipping>' . PHP_EOL;
        }


        foreach ($pseArray as &$pse) {
            if ($pse['PRODUCT_VISIBLE'] == 1) {
                $xmlPse = $this->renderXmlOnePse($feed, $pse, $shippingStr, $checkAvailability);
                if (!empty($xmlPse)) {
                    $this->nb_pse++;
                }else{
                    $this->nb_pse_error++;
                }
                $str .= $xmlPse;
            } else {
                $this->nb_pse_invisible++;
            }
        }

        $str .= '</channel>' . PHP_EOL;
        $str .= '</rss>';
        return $str;
    }

    /**
     * @param GoogleshoppingxmlFeed $feed
     * @param array $pse
     * @param string $shippingStr
     * @param bool $checkAvailability
     * @return string
     */
    protected function renderXmlOnePse($feed, &$pse, $shippingStr, $checkAvailability)
    {
        $str = '<item>' . PHP_EOL;
        $str .= '<g:id>' . $pse['ID'] . '</g:id>' . PHP_EOL;


        // **************** Title ****************

        if (empty($pse['TITLE'])) {
            $this->logger->logError(
                $feed,
                $pse['ID'],
                Translator::getInstance()->trans('Missing product title for the language "%lang"', ['%lang' => $feed->getLang()->getTitle()], GoogleShoppingXml::DOMAIN_NAME),
                Translator::getInstance()->trans('Check that this product has a valid title in this langage.', [], GoogleShoppingXml::DOMAIN_NAME)
            );
            return '';
        }

        $str .= '<g:title>' . $this->xmlSafeEncode($pse['TITLE']) . '</g:title>' . PHP_EOL;


        // **************** Description ****************

        $description = html_entity_decode(trim(strip_tags($pse['DESCRIPTION'])));

        if (empty($description)) {
            $this->logger->logError(
                $feed,
                $pse['ID'],
                Translator::getInstance()->trans('Missing product description for the language "%lang"', ['%lang' => $feed->getLang()->getTitle()], GoogleShoppingXml::DOMAIN_NAME),
                Translator::getInstance()->trans('Check that this product has a valid description in this langage.', [], GoogleShoppingXml::DOMAIN_NAME)
            );
            return '';
        }

        $str .= '<g:description>' . $this->xmlSafeEncode($description) . '</g:description>' . PHP_EOL;


        // **************** URL ****************

        if (empty($pse['URL'])) {
            $this->logger->logError(
                $feed,
                $pse['ID'],
                Translator::getInstance()->trans('Missing product URL', [], GoogleShoppingXml::DOMAIN_NAME)
            );
            return '';
        }

        $str .= '<g:link>' . $this->xmlSafeEncode($pse['URL']) . '</g:link>' . PHP_EOL;


        // **************** Image path ****************

        if (empty($pse['IMAGE_PATH'])) {
            $this->logger->logError(
                $feed,
                $pse['ID'],
                Translator::getInstance()->trans('Missing product image', [], GoogleShoppingXml::DOMAIN_NAME),
                Translator::getInstance()->trans('Please add an image for this product.', [], GoogleShoppingXml::DOMAIN_NAME)
            );
            return '';
        }

        $str .= '<g:image_link>' . $this->xmlSafeEncode($pse['IMAGE_PATH']) . '</g:image_link>' . PHP_EOL;


        // **************** Availability ****************

        if ($checkAvailability && $pse['QUANTITY'] <= 0) {
            $str .= '<g:availability>out of stock</g:availability>' . PHP_EOL;
        } else {
            $str .= '<g:availability>in stock</g:availability>' . PHP_EOL;
        }


        // **************** Price ****************

        if (empty($pse['TAXED_PRICE'])) {
            $this->logger->logError(
                $feed,
                $pse['ID'],
                Translator::getInstance()->trans('Missing product price for the currency "%code"', ['%code' => $feed->getCurrency()->getCode()], GoogleShoppingXml::DOMAIN_NAME),
                Translator::getInstance()->trans('Unable to compute a price for this product and this currency. Specify one manually or check [Apply exchange rates] in the Edit Product page for this currency.', [], GoogleShoppingXml::DOMAIN_NAME)
            );
            return '';
        }

        $formattedTaxedPrice = MoneyFormat::getInstance($this->request)->formatByCurrency($pse['TAXED_PRICE'], null, null, null, $feed->getCurrencyId());

        $str .= '<g:price>' . $formattedTaxedPrice . '</g:price>' . PHP_EOL;

        if (!empty($pse['TAXED_PROMO_PRICE']) && $pse['TAXED_PROMO_PRICE'] < $pse['TAXED_PRICE']) {
            $formattedTaxedPromoPrice = MoneyFormat::getInstance($this->request)->formatByCurrency($pse['TAXED_PROMO_PRICE'], null, null, null, $feed->getCurrencyId());
            $str .= '<g:sale_price>' . $formattedTaxedPromoPrice . '</g:sale_price>' . PHP_EOL;
        }


        // **************** Brand ****************

        if (!$this->hasCustomField($pse, "brand")) {
            if (empty($pse['BRAND_TITLE'])) {
                $this->logger->logError(
                    $feed,
                    $pse['ID'],
                    Translator::getInstance()->trans('Missing product brand for the language "%lang"', ['%lang' => $feed->getLang()->getTitle()], GoogleShoppingXml::DOMAIN_NAME),
                    Translator::getInstance()->trans('The product has no brand or the brand doesn t have a title in this language. If none of your product has a brand, please add a [brand] field with a fixed value in the [Advanded Configuration] tab as this field is required by Google.', [], GoogleShoppingXml::DOMAIN_NAME)
                );
                return '';
            }

            $str .= '<g:brand>' . $this->xmlSafeEncode($pse['BRAND_TITLE']) . '</g:brand>' . PHP_EOL;
        }


        // **************** EAN / GTIN code ****************

        $include_ean = false;

        if (empty($pse['EAN_CODE']) || $this->ean_rule == self::EAN_RULE_NONE) {
            $include_ean = false;
        } elseif ($this->ean_rule == self::EAN_RULE_ALL) {
            $include_ean = true;
        } else {
            if ((new GtinChecker())->isValidGtin($pse['EAN_CODE'])) {
                $include_ean = true;
            } else {
                if ($this->ean_rule == self::EAN_RULE_CHECK_FLEXIBLE) {
                    $include_ean = false;
                } elseif ($this->ean_rule == self::EAN_RULE_CHECK_STRICT) {
                    $this->logger->logError(
                        $feed,
                        $pse['ID'],
                        Translator::getInstance()->trans('Invalid GTIN/EAN code : "%code"', ["%code" => $pse['EAN_CODE']], GoogleShoppingXml::DOMAIN_NAME),
                        Translator::getInstance()->trans('The product s identification code seems invalid. You can set a valid EAN code in the Edit product page or disable the verification in the [Advanced configuration] tab.', [], GoogleShoppingXml::DOMAIN_NAME)
                    );
                    return '';
                }
            }
        }

        if ($include_ean) {
            $str .= '<g:gtin>' . $pse['EAN_CODE'] . '</g:gtin>' . PHP_EOL;
            $str .= '<g:identifier_exists>yes</g:identifier_exists>' . PHP_EOL;
        } else {
            $str .= '<g:identifier_exists>no</g:identifier_exists>' . PHP_EOL;
        }

        $str .= '<g:item_group_id>' . $pse['REF_PRODUCT'] . '</g:item_group_id>' . PHP_EOL;

        $str .= $shippingStr;


        // **************** Categories ****************

        if (!empty($pse['GOOGLE_CATEGORY'])) {
            $str .= '<g:google_product_category>' . $this->xmlSafeEncode($pse['GOOGLE_CATEGORY']) . '</g:google_product_category>' . PHP_EOL;
        } else {
            $this->logger->logWarning(
                $feed,
                $pse['ID'],
                Translator::getInstance()->trans('No Google category related to the Thelia category "%cat".', ['%cat' => $pse['CATEGORY_PATH']], GoogleShoppingXml::DOMAIN_NAME),
                Translator::getInstance()->trans('This product s category is not related to any Google category. It is required by Google for most products. Please add one in the [Google Taxonomy] tab.', [], GoogleShoppingXml::DOMAIN_NAME)
            );
        }

        if (!empty($pse['CATEGORY_PATH'])) {
            $str .= '<g:product_type>' . $this->xmlSafeEncode($pse['CATEGORY_PATH']) . '</g:product_type>' . PHP_EOL;
        }

        if (!$this->hasCustomField($pse, "condition")) {
            $str .= '<g:condition>new</g:condition>' . PHP_EOL;
        }

        foreach ($pse['CUSTOM_FIELD_ARRAY'] as $field) {
            $str .= '<g:' . $field['FIELD_NAME'] . '>' . $this->xmlSafeEncode($field['FIELD_VALUE']) . '</g:' . $field['FIELD_NAME'] . '>' . PHP_EOL;
        }

        $additionalFieldEvent = new AdditionalFieldEvent($pse['ID']);
        $this->eventDispatcher->dispatch(AdditionalFieldEvent::ADD_FIELD_EVENT, $additionalFieldEvent);

        foreach ($additionalFieldEvent->getFields() as $fieldName => $fieldValue) {
            $str .= "<g:{$fieldName}>{$this->xmlSafeEncode($fieldValue)}</g:{$fieldName}>" . PHP_EOL;
        }

        return $str . '</item>' . PHP_EOL;
    }

    protected function xmlSafeEncode($str)
    {
        return htmlspecialchars($str, ENT_XML1);
    }

    protected function hasCustomField($pse, $fieldName)
    {
        foreach ($pse['CUSTOM_FIELD_ARRAY'] as $field) {
            if ($field['FIELD_NAME'] == $fieldName) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param GoogleshoppingxmlFeed $feed
     */
    protected function getProductItems($feed, $limit = null, $offset = null)
    {
        $sql = 'SELECT 

                pse.ID AS ID,
                product.ID AS ID_PRODUCT,
                product.REF AS REF_PRODUCT,
                product.VISIBLE AS PRODUCT_VISIBLE,
                product_i18n.TITLE AS TITLE,
                product_i18n.DESCRIPTION AS DESCRIPTION,
                product.BRAND_ID AS BRAND_ID,
                pse.QUANTITY AS QUANTITY,
                pse.EAN_CODE AS EAN_CODE,
                product.TAX_RULE_ID AS TAX_RULE_ID
                FROM product_sale_elements AS pse
                INNER JOIN product ON (pse.PRODUCT_ID = product.ID)
                LEFT OUTER JOIN product_i18n ON (pse.PRODUCT_ID = product_i18n.ID AND product_i18n.LOCALE = :locale)
                INNER JOIN product_category ON (pse.PRODUCT_ID = product_category.product_id)
                INNER JOIN googleshoppingxml_ignore_category ON (googleshoppingxml_ignore_category.category_id = product_category.category_id)
                WHERE  product.VISIBLE = 1
                AND googleshoppingxml_ignore_category.is_exportable = 1
                GROUP BY pse.ID';

        $limit = $this->checkPositiveInteger($limit);
        $offset = $this->checkPositiveInteger($offset);

        if ($limit) {
            $sql .= " LIMIT $limit";
        }

        if ($offset) {
            if (!$limit) {
                $sql .= " LIMIT 99999999999";
            }
            $sql .= " OFFSET $offset";
        }

        $con = Propel::getConnection();
        $stmt = $con->prepare($sql);
        $stmt->bindValue(':locale', $feed->getLang()->getLocale(), \PDO::PARAM_STR);

        $stmt->execute();
        return $stmt;
    }

    protected function checkPositiveInteger($var)
    {
        $var = filter_var($var, FILTER_VALIDATE_INT);
        return ($var !== false && $var >= 0) ? $var : null;
    }

    /**
     * @param GoogleshoppingxmlFeed $feed
     * @param array $pseArray
     */
    protected function injectGoogleCategories(&$pse, $feed)
    {
        // Add google category or parent's and thelia category path
        $productCategory = ProductCategoryQuery::create()
            ->filterByProductId($pse['ID_PRODUCT'])
            ->filterByDefaultCategory(1)
            ->findOne();

        $pse['CATEGORY_ID'] = $productCategory ? $productCategory->getCategoryId() : null;

        $hasReachRoot = false;
        $categoryId = $pse['CATEGORY_ID'];
        if (empty($categoryId)) {
            $this->logger->logError(
                $feed,
                $pse['ID'],
                Translator::getInstance()->trans('No Thelia default category found for this product.', [], GoogleShoppingXml::DOMAIN_NAME),
                Translator::getInstance()->trans('This product does not have any default category.', [], GoogleShoppingXml::DOMAIN_NAME)
            );
            return;
        }

        $categoryRow = $this->theliaCategories[$categoryId];

        if ($categoryRow['PATH'] != null) {
            $pse['CATEGORY_PATH'] = $categoryRow['PATH'];
        } else {
            $pse['CATEGORY_PATH'] = null;
        }

        while (!array_key_exists($categoryId, $this->googleCategories)) {
            $parent = $this->theliaCategories[$categoryId]['PARENT'];
            if ($parent == 0) {
                $hasReachRoot = true;
                break;
            }

            $categoryId = $parent;
        }

        if (!$hasReachRoot) {
            $pse['GOOGLE_CATEGORY'] = $this->googleCategories[$categoryId];
        }

    }


    protected function recursiveSetCategoryPath(&$theliaCategories, $categoryRow)
    {
        if ($categoryRow['PARENT'] == 0 || $categoryRow['PATH'] != null || $categoryRow['TITLE'] == null) {
            if ($categoryRow['PARENT'] == 0 && $categoryRow['PATH'] == null && $categoryRow['TITLE'] != null) {
                $theliaCategories[$categoryRow['ID']]['PATH'] = $categoryRow['TITLE'];
            }
            return;
        }

        $parentRow = $theliaCategories[$categoryRow['PARENT']];

        if ($parentRow['PATH'] == null) {
            $this->recursiveSetCategoryPath($theliaCategories, $parentRow);
            $parentRow = $theliaCategories[$categoryRow['PARENT']];
        }

        if ($parentRow['PATH'] != null) {
            $theliaCategories[$categoryRow['ID']]['PATH'] = $parentRow['PATH'] . ' > ' . $categoryRow['TITLE'];
        }
    }


    /**
     * @param GoogleshoppingxmlFeed $feed
     * @param array $pseArray
     */
    protected function injectUrls(&$pse, $feed, URL $urlManager)
    {
        $rewrittenUrl = RewritingUrlQuery::create()
            ->filterByViewId($pse['ID_PRODUCT'])
            ->filterByView('product')
            ->filterByViewLocale($feed->getLang()->getLocale())
            ->filterByRedirected(null, Criteria::ISNULL)
            ->findOne();

        if ($rewrittenUrl == null) {
            $pse['URL'] = $urlManager->retrieve('product', $pse['ID_PRODUCT'], $feed->getLang()->getLocale())->toString();
        } else {
            $pse['URL'] = $urlManager->absoluteUrl($rewrittenUrl->getUrl());
        }
    }


    /**
     * @param GoogleshoppingxmlFeed $feed
     * @param array $pseArray
     */
    protected function injectTaxedPrices(&$pse, &$taxCalculatorsArray, $feed, $taxRulesArray)
    {
        $taxRuleId = $pse['TAX_RULE_ID'];
        $taxRule = $taxRulesArray[$taxRuleId];

        if (!array_key_exists($taxRuleId, $taxCalculatorsArray)) {
            $calculator = new Calculator();
            $calculator->loadTaxRuleWithoutProduct($taxRule, $feed->getCountry());
            $taxCalculatorsArray[$taxRuleId] = $calculator;
        } else {
            $calculator = $taxCalculatorsArray[$taxRuleId];
        }

        $pseDefaultPrice = ProductPriceQuery::create()
            ->filterByProductSaleElementsId($pse['ID'])
            ->filterByFromDefaultCurrency(1)
            ->findOne();
        $pseCurrencyPrice = ProductPriceQuery::create()
            ->filterByProductSaleElementsId($pse['ID'])
            ->filterByCurrencyId($feed->getCurrencyId())
            ->findOne();

        $pse['PRICE'] = $pseDefaultPrice ? $pseDefaultPrice->getPrice() * $feed->getCurrency()->getRate() : null;
        $pse['PROMO_PRICE'] = $pseDefaultPrice ? $pseDefaultPrice->getPromoPrice() * $feed->getCurrency()->getRate() : null;

        if (null !== $pseCurrencyPrice) {
            $pse['PRICE'] = $pseCurrencyPrice->getPrice();
            $pse['PROMO_PRICE'] = $pseCurrencyPrice->getPromoPrice();
        }

        $pse['TAXED_PRICE'] = $pse['PRICE'] !== null ? $calculator->getTaxedPrice($pse['PRICE']) : null;
        $pse['TAXED_PROMO_PRICE'] = $pse['PROMO_PRICE'] !== null ? $calculator->getTaxedPrice($pse['PROMO_PRICE']) : null;

    }

    /**
     * @param GoogleshoppingxmlFeed $feed
     * @param array $pseArray
     */
    protected function injectCustomAssociationFields(&$pse, $feed, $fieldAssociationCollection)
    {
        $attributesArray = [];
        $featuresArray = [];

        $customFieldArray = [];
        /** @var GoogleshoppingxmlGoogleFieldAssociation $fieldAssociation */
        foreach ($fieldAssociationCollection as $fieldAssociation) {
            $found = false;
            $customField = ['FIELD_NAME' => $fieldAssociation->getGoogleField()];
            switch ($fieldAssociation->getAssociationType()) {
                case GoogleFieldAssociationController::ASSO_TYPE_FIXED_VALUE:
                    $customField['FIELD_VALUE'] = $fieldAssociation->getFixedValue();
                    $found = true;
                    break;
                case GoogleFieldAssociationController::ASSO_TYPE_RELATED_TO_THELIA_ATTRIBUTE:
                    $idAttribute = $fieldAssociation->getIdRelatedAttribute();
                    if (!array_key_exists($idAttribute, $attributesArray)) {
                        $attributesArray[$idAttribute] = $this->getArrayAttributesConcatValues($feed->getLang()->getLocale(), $idAttribute);
                    }
                    if ($found = array_key_exists($pse['ID'], $attributesArray[$idAttribute])) {
                        $customField['FIELD_VALUE'] = $attributesArray[$idAttribute][$pse['ID']];
                    }
                    break;
                case GoogleFieldAssociationController::ASSO_TYPE_RELATED_TO_THELIA_FEATURE:
                    $idFeature = $fieldAssociation->getIdRelatedFeature();
                    if (!array_key_exists($idFeature, $featuresArray)) {
                        $featuresArray[$idFeature] = $this->getArrayFeaturesConcatValues($feed->getLang()->getLocale(), $idFeature);
                    }
                    if ($found = array_key_exists($pse['ID_PRODUCT'], $featuresArray[$idFeature])) {
                        $customField['FIELD_VALUE'] = $featuresArray[$idFeature][$pse['ID_PRODUCT']];
                    }
                    break;
            }
            if ($found) {
                $customFieldArray[] = $customField;
            }
        }
        $pse['CUSTOM_FIELD_ARRAY'] = $customFieldArray;
    }

    /**
     * @param GoogleshoppingxmlFeed $feed
     * @param array $pseArray
     */
    protected function injectAttributesInTitle(&$pse, $feed)
    {

        $attributesConcatArray = $this->getArrayAttributesConcatValues($feed->getLang()->getLocale(), null, ' - ');

        if (array_key_exists($pse['ID'], $attributesConcatArray)) {
            $pse['TITLE'] .= ' - ' . $attributesConcatArray[$pse['ID']];
        }


    }


    /**
     * @param array $pseArray
     */
    protected function injectImages(&$pse)
    {
        $productImageDefault = ProductImageQuery::create()
            ->filterByProductId($pse['ID_PRODUCT'])
            ->filterByPosition(1)
            ->findOne();
        $productImagePse = ProductImageQuery::create()
            ->useProductSaleElementsProductImageQuery()
            ->filterByProductSaleElementsId($pse['ID'])
            ->endUse()
            ->findOne();

        $pse['IMAGE_NAME'] = $productImageDefault ? $productImageDefault->getFile() : null;

        if (null !== $productImagePse) {
            $pse['IMAGE_NAME'] = $productImagePse ? $productImagePse->getFile() : null;
        }

        if ($pse['IMAGE_NAME'] != null) {
            $imageEvent = $this->createImageEvent($pse['IMAGE_NAME'], 'product');
            try {
                $this->eventDispatcher->dispatch(TheliaEvents::IMAGE_PROCESS, $imageEvent);
                $pse['IMAGE_PATH'] = $imageEvent->getFileUrl();
            } catch (\Exception $e) {
                $pse['IMAGE_PATH'] = null;
            }
        } else {
            $pse['IMAGE_PATH'] = null;
        }
    }

    protected function injectBrand(&$pse, $feed)
    {
        $brandDefault = BrandI18nQuery::create()
            ->filterById($pse['BRAND_ID'])
            ->findOne();
        $brandWithLocal = BrandI18nQuery::create()
            ->filterByLocale($feed->getLang()->getLocale())
            ->filterById($pse['BRAND_ID'])
            ->findOne();

        $pse['BRAND_TITLE'] = $brandDefault ? $brandDefault->getTitle() : null;

        if ($brandWithLocal !== null) {
            $pse['BRAND_TITLE'] = $brandWithLocal ? $brandWithLocal->getLocale() : null;
        }
    }


    /**
     * @param string $imageFile
     * @param string $type
     * @return ImageEvent
     */
    protected function createImageEvent($imageFile, $type)
    {
        $imageEvent = new ImageEvent();
        $baseSourceFilePath = ConfigQuery::read('images_library_path');
        if ($baseSourceFilePath === null) {
            $baseSourceFilePath = THELIA_LOCAL_DIR . 'media' . DS . 'images';
        } else {
            $baseSourceFilePath = THELIA_ROOT . $baseSourceFilePath;
        }
        // Put source image file path
        $sourceFilePath = sprintf(
            '%s/%s/%s',
            $baseSourceFilePath,
            $type,
            $imageFile
        );
        $imageEvent->setSourceFilepath($sourceFilePath);
        $imageEvent->setCacheSubdirectory($type);
        $imageEvent->setResizeMode(Image::EXACT_RATIO_WITH_BORDERS);
        return $imageEvent;
    }


    protected function getArrayAttributesConcatValues($locale, $attribute_id = null, $separator = '/')
    {
        $timestamp_debut = microtime(true);


        $con = Propel::getConnection();

        $sql = 'SELECT attribute_combination.product_sale_elements_id AS PSE_ID, GROUP_CONCAT(attribute_av_i18n.title SEPARATOR \'' . $separator . '\') AS CONCAT FROM attribute_combination
                INNER JOIN attribute_av_i18n ON (attribute_combination.attribute_av_id = attribute_av_i18n.id)
                WHERE attribute_av_i18n.locale = :locale';

        if ($attribute_id != null) {
            $sql .= ' AND attribute_combination.attribute_id = :attrid';
        }

        $sql .= ' GROUP BY attribute_combination.product_sale_elements_id';

        $stmt = $con->prepare($sql);
        $stmt->bindValue(':locale', $locale, \PDO::PARAM_STR);
        if ($attribute_id != null) {
            $stmt->bindValue(':attrid', $attribute_id, \PDO::PARAM_INT);
        }

        $stmt->execute();

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);



        $attrib_by_pse = array();
        foreach ($rows as $row) {
            $attrib_by_pse[$row['PSE_ID']] = $row['CONCAT'];
        }
        $timestamp_fin = microtime(true);

        $difference_ms = $timestamp_fin - $timestamp_debut;

        return $attrib_by_pse;
    }


    protected function getArrayFeaturesConcatValues($locale, $feature_id)
    {
        $con = Propel::getConnection();

        $sql = 'SELECT feature_product.product_id AS PRODUCT_ID, GROUP_CONCAT(feature_av_i18n.title SEPARATOR \'/\') AS CONCAT FROM feature_product
                INNER JOIN feature_av_i18n ON (feature_product.feature_av_id = feature_av_i18n.id)
                WHERE feature_av_i18n.locale = :locale
                AND feature_product.feature_id = :featid
                GROUP BY feature_product.product_id';

        $stmt = $con->prepare($sql);
        $stmt->bindValue(':locale', $locale, \PDO::PARAM_STR);
        $stmt->bindValue(':featid', $feature_id, \PDO::PARAM_INT);

        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $attrib_by_pse = array();
        foreach ($rows as $row) {
            $attrib_by_pse[$row['PRODUCT_ID']] = $row['CONCAT'];
        }
        return $attrib_by_pse;
    }

    /**
     * @param GoogleshoppingxmlFeed $feed
     * @return array
     */
    protected function buildShippingArray($feed)
    {
        $resultArray = [];

        $shippingInfoArray = $this->getShippings($feed);

        foreach ($shippingInfoArray as $moduleTitle => $postagePrice) {
            $shippingItem = [];
            $shippingItem['country_code'] = $feed->getCountry()->getIsoalpha2();
            $shippingItem['service'] = $moduleTitle;
            $shippingItem['price'] = $postagePrice;
            $shippingItem['currency_id'] = $feed->getCurrencyId();
            $resultArray[] = $shippingItem;
        }

        if (empty($resultArray)) {
            $this->logger->logError(
                $feed,
                null,
                Translator::getInstance()->trans('No shipping informations.', [], GoogleShoppingXml::DOMAIN_NAME),
                Translator::getInstance()->trans('The feed doesn t have any shippings informations. Check that at least one delivery module covers the country aimed by your feed.', [], GoogleShoppingXml::DOMAIN_NAME)
            );
        }

        return $resultArray;
    }

    /**
     * @param GoogleshoppingxmlFeed $feed
     * @return array
     */
    protected function getShippings($feed)
    {
        $country = $feed->getCountry();

        $search = ModuleQuery::create()
            ->filterByActivate(1)
            ->filterByType(BaseModule::DELIVERY_MODULE_TYPE, Criteria::EQUAL)
            ->find();

        $deliveries = array();

        /** @var Module $deliveryModule */
        foreach ($search as $deliveryModule) {
            $deliveryModule->setLocale($feed->getLang()->getLocale());

            $areaDeliveryModule = AreaDeliveryModuleQuery::create()
                ->findByCountryAndModule($country, $deliveryModule);

            if (null === $areaDeliveryModule) {
                continue;
            }

            $moduleInstance = $deliveryModule->getDeliveryModuleInstance($this->container);

            if ($moduleInstance->isValidDelivery($country)) {
                $postage = OrderPostage::loadFromPostage($moduleInstance->getPostage($country));
                $price = $postage->getAmount() * $feed->getCurrency()->getRate();

                $deliveries[$deliveryModule->getTitle()] = $price;
            }
        }

        return $deliveries;
    }

    protected function getGoogleAndTheliaCategories($feed)
    {
        $con = Propel::getConnection();

        // Get Google categories
        $sql = 'SELECT thelia_category_id AS ID_CAT_THELIA, google_category AS GOOGLE_CAT FROM googleshoppingxml_taxonomy WHERE lang_id = :p1';

        $stmt = $con->prepare($sql);
        $stmt->bindValue(':p1', $feed->getLangId(), \PDO::PARAM_INT);

        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->googleCategories = array();
        foreach ($rows as $row) {
            $this->googleCategories[$row['ID_CAT_THELIA']] = $row['GOOGLE_CAT'];
        }

        // Get Thelia category hierarchy

        $sql = 'SELECT category.ID AS ID, category.PARENT AS PARENT, COALESCE(cati18n_with_locale.TITLE, cati18n_without_locale.TITLE) AS TITLE FROM category
            LEFT OUTER JOIN category_i18n cati18n_with_locale ON (category.id = cati18n_with_locale.id AND cati18n_with_locale.locale = :locale)
            LEFT OUTER JOIN category_i18n cati18n_without_locale ON (category.id = cati18n_without_locale.id)';

        $stmt = $con->prepare($sql);
        $stmt->bindValue(':locale', $feed->getLang()->getLocale(), \PDO::PARAM_STR);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->theliaCategories = array();
        foreach ($rows as $row) {
            $row['PATH'] = null;
            $this->theliaCategories[$row['ID']] = $row;
        }

        foreach ($this->theliaCategories as $row) {
            $this->recursiveSetCategoryPath($this->theliaCategories, $row);
        }
    }

    protected function getTaxedRules()
    {
        $taxRulesCollection = TaxRuleQuery::create()->find();
        $taxRulesArray = [];
        /** @var TaxRule $taxRule * */
        foreach ($taxRulesCollection as $taxRule) {
            $taxRulesArray[$taxRule->getId()] = $taxRule;
        }

        return $taxRulesArray;
    }

    protected function getFieldAssociationCollection($feed)
    {
        $fieldAssociationCollection = GoogleshoppingxmlGoogleFieldAssociationQuery::create()->find();

        foreach ($fieldAssociationCollection as $fieldAssociation) {
            $fieldName = $fieldAssociation->getGoogleField();
            if (in_array($fieldName, GoogleFieldAssociationController::FIELDS_NATIVELY_DEFINED)) {
                $this->logger->logWarning(
                    $feed,
                    null,
                    Translator::getInstance()->trans('XML field "%field" already defined', ['%field' => $fieldName], GoogleShoppingXml::DOMAIN_NAME),
                    Translator::getInstance()->trans('You manually specified a field that is already defined by the module and that does not support overriding. It may cause issues as the field is defined twice.', [], GoogleShoppingXml::DOMAIN_NAME)
                );
            } else if (!in_array($fieldName, GoogleFieldAssociationController::GOOGLE_FIELD_LIST)) {
                $this->logger->logWarning(
                    $feed,
                    null,
                    Translator::getInstance()->trans('Unknown XML field "%field".', ['%field' => $fieldName], GoogleShoppingXml::DOMAIN_NAME),
                    Translator::getInstance()->trans('You manually specified a field that does not seem to be a valid Google XML field. You may have a typo that will cause issues.', [], GoogleShoppingXml::DOMAIN_NAME)
                );
            }
        }

        return $fieldAssociationCollection;
    }
}