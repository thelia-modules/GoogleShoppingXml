<?php

namespace GoogleShoppingXml\Controller;

use GoogleShoppingXml\Model\GoogleshoppingxmlFeed;
use GoogleShoppingXml\Model\GoogleshoppingxmlFeedQuery;
use GoogleShoppingXml\Model\GoogleshoppingxmlGoogleFieldAssociation;
use GoogleShoppingXml\Model\GoogleshoppingxmlGoogleFieldAssociationQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Propel;
use Thelia\Action\Image;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Event\Image\ImageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Model\AreaDeliveryModuleQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\Currency;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderPostage;
use Thelia\Model\TaxRule;
use Thelia\Model\TaxRuleQuery;
use Thelia\Module\BaseModule;
use Thelia\TaxEngine\Calculator;
use Thelia\Tools\MoneyFormat;
use Thelia\Tools\URL;

class FeedXmlController extends BaseAdminController
{
    public function getFeedXmlAction($feedId)
    {
        $feed = GoogleshoppingxmlFeedQuery::create()->findOneById($feedId);

        $request = $this->getRequest();

        $limit = $request->get('limit', null);
        $offset = $request->get('offset', null);

        if ($feed == null) {
            return $this->pageNotFound();
        }

        $shippingArray = $this->buildShippingArray($feed);

        $pse_array = $this->getProductItems($feed, $limit, $offset);
        $this->injectGoogleCategories($pse_array, $feed);
        $this->injectUrls($pse_array, $feed);
        $this->injectTaxedPrices($pse_array, $feed);
        $this->injectCustomAssociationFields($pse_array, $feed);
        $this->injectAttributesInTitle($pse_array, $feed);
        $this->injectImages($pse_array);

        $content = $this->renderXmlAll($feed, $pse_array, $shippingArray);

        $response = new Response();
        $response->setContent($content);
        $response->headers->set('Content-Type', 'application/xml');

        return $response;
    }

    protected function renderXmlAll($feed, &$pse_array, $shippingArray)
    {
        $checkAvailability = ConfigQuery::checkAvailableStock();

        $str = '<?xml version="1.0"?>'.PHP_EOL;
        $str .= '<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">'.PHP_EOL;
        $str .= '<channel>'.PHP_EOL;
        $str .= '<title>'.ConfigQuery::getStoreName().'</title>'.PHP_EOL;
        $str .= '<link>'.$this->xmlSafeEncode(URL::getInstance()->getIndexPage()).'</link>'.PHP_EOL;
        $str .= '<description>'.$this->xmlSafeEncode(ConfigQuery::getStoreDescription()).'</description>'.PHP_EOL;

        $shippingStr = '';
        foreach ($shippingArray as $shipping) {
            $shippingStr .= '<g:shipping>'.PHP_EOL;
            $shippingStr .= '<g:country>'.$shipping['country_code'].'</g:country>'.PHP_EOL;
            $shippingStr .= '<g:service>'.$shipping['service'].'</g:service>'.PHP_EOL;
            $formattedPrice = MoneyFormat::getInstance($this->getRequest())->formatByCurrency($shipping['price'], null, null, null, $shipping['currency_id']);
            $shippingStr .= '<g:price>'. $formattedPrice . '</g:price>'.PHP_EOL;
            $shippingStr .= '</g:shipping>'.PHP_EOL;
        }

        foreach ($pse_array as &$pse) {
            $str .= $this->renderXmlOnePse($feed, $pse, $shippingStr, $checkAvailability);
        }

        $str .= '</channel>'.PHP_EOL;
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
        $str = '<item>'.PHP_EOL;
        $str .= '<g:id>'.$pse['ID'].'</g:id>'.PHP_EOL;
        $str .= '<g:title>'.$this->xmlSafeEncode($pse['TITLE']).'</g:title>'.PHP_EOL;
        $str .= '<g:description>'.$this->xmlSafeEncode(html_entity_decode(trim(strip_tags($pse['DESCRIPTION'])))).'</g:description>'.PHP_EOL;
        $str .= '<g:link>'.$this->xmlSafeEncode($pse['URL']).'</g:link>'.PHP_EOL;

        if (empty($pse['IMAGE_PATH'])) { // Mandatory field
            return '';
        }

        $str .= '<g:image_link>'.$this->xmlSafeEncode($pse['IMAGE_PATH']).'</g:image_link>'.PHP_EOL;

        if ($checkAvailability && $pse['QUANTITY'] <= 0) {
            $str .= '<g:availability>out of stock</g:availability>'.PHP_EOL;
        } else {
            $str .= '<g:availability>in stock</g:availability>'.PHP_EOL;
        }

        $formattedTaxedPrice = MoneyFormat::getInstance($this->getRequest())->formatByCurrency($pse['TAXED_PRICE'], null, null, null, $feed->getCurrencyId());

        $str .= '<g:price>'.$formattedTaxedPrice.'</g:price>'.PHP_EOL;

        if ($pse['TAXED_PROMO_PRICE'] != 0 && $pse['TAXED_PROMO_PRICE'] < $pse['TAXED_PRICE']) {
            $formattedTaxedPromoPrice = MoneyFormat::getInstance($this->getRequest())->formatByCurrency($pse['TAXED_PROMO_PRICE'], null, null, null, $feed->getCurrencyId());
            $str .= '<g:sale_price>'.$formattedTaxedPromoPrice.'</g:sale_price>'.PHP_EOL;
        }

        if (!$this->hasCustomField($pse, "brand")) {
            $str .= '<g:brand>' . $this->xmlSafeEncode($pse['BRAND_TITLE']) . '</g:brand>' . PHP_EOL;
        }

        if (!empty($pse['EAN_CODE'])) {
            $str .= '<g:gtin>'.$pse['EAN_CODE'].'</g:gtin>'.PHP_EOL;
            $str .= '<g:identifier_exists>yes</g:identifier_exists>'.PHP_EOL;
        } else {
            $str .= '<g:identifier_exists>no</g:identifier_exists>'.PHP_EOL;
        }

        $str .= '<g:item_group_id>'.$pse['REF_PRODUCT'].'</g:item_group_id>'.PHP_EOL;

        $str .= $shippingStr;

        if (!empty($pse['GOOGLE_CATEGORY'])) {
            $str .= '<g:google_product_category>'.$this->xmlSafeEncode($pse['GOOGLE_CATEGORY']).'</g:google_product_category>'.PHP_EOL;
        }

        if (!empty($pse['CATEGORY_PATH'])) {
            $str .= '<g:product_type>'.$this->xmlSafeEncode($pse['CATEGORY_PATH']).'</g:product_type>'.PHP_EOL;
        }

        if (!$this->hasCustomField($pse, "condition")) {
            $str .= '<g:condition>new</g:condition>'.PHP_EOL;
        }

        foreach ($pse['CUSTOM_FIELD_ARRAY'] as $field) {
            $str .= '<g:'.$field['FIELD_NAME'].'>'.$this->xmlSafeEncode($field['FIELD_VALUE']).'</g:'.$field['FIELD_NAME'].'>'.PHP_EOL;
        }

        return $str.'</item>'.PHP_EOL;
    }

    protected function xmlSafeEncode($str)
    {
        return htmlspecialchars($str, ENT_XML1);
    }

    protected function hasCustomField($pse, $field_name)
    {
        foreach ($pse['CUSTOM_FIELD_ARRAY'] as $field) {
            if ($field['FIELD_NAME'] == $field_name) {
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
                product_i18n.TITLE AS TITLE,
                product_i18n.DESCRIPTION AS DESCRIPTION,
                brand_i18n.TITLE AS BRAND_TITLE,
                pse.QUANTITY AS QUANTITY,
                pse.EAN_CODE AS EAN_CODE,
                product_category.CATEGORY_ID AS CATEGORY_ID,
                product.TAX_RULE_ID AS TAX_RULE_ID,
                ROUND(CASE WHEN ISNULL(product_price.PRICE) OR product_price.FROM_DEFAULT_CURRENCY = 1 THEN product_price.PRICE * :currate ELSE product_price.PRICE END, 6) AS PRICE,
                ROUND(CASE WHEN ISNULL(product_price.PRICE) OR product_price.FROM_DEFAULT_CURRENCY = 1 THEN product_price.PROMO_PRICE  * :currate ELSE product_price.PROMO_PRICE END, 6) AS PROMO_PRICE,
                rewriting_url.url AS REWRITTEN_URL,
                product_image.file AS IMAGE_NAME
                
                FROM product_sale_elements AS pse
                
                INNER JOIN product ON (pse.PRODUCT_ID = product.ID)
                INNER JOIN product_i18n ON (pse.PRODUCT_ID = product_i18n.ID)
                INNER JOIN product_category ON (pse.PRODUCT_ID = product_category.PRODUCT_ID)
                INNER JOIN product_price ON (pse.ID = product_price.PRODUCT_SALE_ELEMENTS_ID)
                LEFT OUTER JOIN brand_i18n ON (product.BRAND_ID = brand_i18n.ID AND brand_i18n.LOCALE = :locale)
                LEFT OUTER JOIN rewriting_url ON (pse.PRODUCT_ID = rewriting_url.VIEW_ID AND rewriting_url.view = \'product\' AND rewriting_url.view_locale = :locale AND rewriting_url.redirected IS NULL)
                LEFT OUTER JOIN product_image ON (pse.PRODUCT_ID = product_image.PRODUCT_ID AND product_image.POSITION = 1)
                
                WHERE product_i18n.LOCALE = :locale
                AND product_category.DEFAULT_CATEGORY = 1
                AND product_price.CURRENCY_ID = :currid
                
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
        $stmt->bindValue(':currid', $feed->getCurrencyId(), \PDO::PARAM_INT);
        $stmt->bindValue(':currate', $feed->getCurrency()->getRate(), \PDO::PARAM_STR);

        $stmt->execute();
        $pse_array = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $pse_array;
    }

    protected function checkPositiveInteger($var)
    {
        $var = filter_var($var, FILTER_VALIDATE_INT);
        return ($var !== false && $var >= 0) ? $var : null;
    }

    /**
     * @param GoogleshoppingxmlFeed $feed
     * @param array $pse_array
     */
    protected function injectGoogleCategories(&$pse_array, $feed)
    {
        $con = Propel::getConnection();

        // Get Google categories

        $sql = 'SELECT thelia_category_id AS ID_CAT_THELIA, google_category AS GOOGLE_CAT FROM googleshoppingxml_taxonomy WHERE lang_id = :p1';

        $stmt = $con->prepare($sql);
        $stmt->bindValue(':p1', $feed->getLangId(), \PDO::PARAM_INT);

        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $google_categories = array();
        foreach ($rows as $row) {
            $google_categories[$row['ID_CAT_THELIA']] = $row['GOOGLE_CAT'];
        }

        // Get Thelia category hierarchy

        $sql = 'SELECT category.id AS ID, category.parent AS PARENT, category_i18n.title AS TITLE FROM category
                LEFT OUTER JOIN category_i18n ON (category.id = category_i18n.id AND category_i18n.locale = :locale)';

        $stmt = $con->prepare($sql);
        $stmt->bindValue(':locale', $feed->getLang()->getLocale(), \PDO::PARAM_STR);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $thelia_categories = array();
        foreach ($rows as $row) {
            $row['PATH'] = null;
            $thelia_categories[$row['ID']] = $row;
        }

        foreach ($thelia_categories as $row) {
            $this->recursiveSetCategoryPath($thelia_categories, $row);
        }


        // Add google category or parent's and thelia category path

        foreach ($pse_array as &$pse) {
            $has_reach_root = false;
            $category_id = $pse['CATEGORY_ID'];

            $category_row = $thelia_categories[$category_id];

            if ($category_row['PATH'] != null) {
                $pse['CATEGORY_PATH'] = $category_row['PATH'];
            }

            while (!array_key_exists($category_id, $google_categories)) {
                $parent = $thelia_categories[$category_id]['PARENT'];
                if ($parent == 0) {
                    $has_reach_root = true;
                    break;
                }

                $category_id = $parent;
            }

            if (!$has_reach_root) {
                $pse['GOOGLE_CATEGORY'] = $google_categories[$category_id];
            }
        }
    }


    protected function recursiveSetCategoryPath(&$thelia_categories, $category_row)
    {
        if ($category_row['PARENT'] == 0 || $category_row['PATH'] != null || $category_row['TITLE'] == null) {
            if ($category_row['PARENT'] == 0 && $category_row['PATH'] == null && $category_row['TITLE'] != null) {
                $thelia_categories[$category_row['ID']]['PATH'] = $category_row['TITLE'];
            }
            return;
        }

        $parent_row = $thelia_categories[$category_row['PARENT']];

        if ($parent_row['PATH'] != null) {
            $this->recursiveSetCategoryPath($thelia_categories, $parent_row);
        }

        if ($parent_row['PATH'] != null) {
            $thelia_categories[$category_row['ID']]['PATH'] = $parent_row['PATH'] . ' > ' . $category_row['TITLE'];
        }
    }


    /**
     * @param GoogleshoppingxmlFeed $feed
     * @param array $pse_array
     */
    protected function injectUrls(&$pse_array, $feed)
    {
        $urlManager = URL::getInstance();
        foreach ($pse_array as &$pse) {
            if ($pse['REWRITTEN_URL'] == null) {
                $pse['URL'] = $urlManager->retrieve('product', $pse['ID_PRODUCT'], $feed->getLang()->getLocale())->toString();
            } else {
                $pse['URL'] = $urlManager->absoluteUrl($pse['REWRITTEN_URL']);
            }
        }
    }


    /**
     * @param GoogleshoppingxmlFeed $feed
     * @param array $pse_array
     */
    protected function injectTaxedPrices(&$pse_array, $feed)
    {
        $tax_rules_collection = TaxRuleQuery::create()->find();
        $tax_rules_array = [];
        /** @var TaxRule $tax_rule **/
        foreach ($tax_rules_collection as $tax_rule) {
            $tax_rules_array[$tax_rule->getId()] = $tax_rule;
        }

        $tax_calculators_array = [];

        foreach ($pse_array as &$pse) {
            $tax_rule_id = $pse['TAX_RULE_ID'];
            $tax_rule = $tax_rules_array[$tax_rule_id];

            if (!array_key_exists($tax_rule_id, $tax_calculators_array)) {
                $calculator = new Calculator();
                $calculator->loadTaxRuleWithoutProduct($tax_rule, $feed->getCountry());
                $tax_calculators_array[$tax_rule_id] = $calculator;
            } else {
                $calculator = $tax_calculators_array[$tax_rule_id];
            }

            $pse['TAXED_PRICE'] = $calculator->getTaxedPrice($pse['PRICE']);
            $pse['TAXED_PROMO_PRICE'] = $calculator->getTaxedPrice($pse['PROMO_PRICE']);
        }
    }

    /**
     * @param GoogleshoppingxmlFeed $feed
     * @param array $pse_array
     */
    protected function injectCustomAssociationFields(&$pse_array, $feed)
    {
        $attributes_array = [];
        $features_array = [];

        $fieldAssociationCollection = GoogleshoppingxmlGoogleFieldAssociationQuery::create()->find();

        foreach ($pse_array as &$pse) {
            $custom_field_array = [];
            /** @var GoogleshoppingxmlGoogleFieldAssociation $fieldAssociation */
            foreach ($fieldAssociationCollection as $fieldAssociation) {
                $found = false;
                $custom_field = ['FIELD_NAME' => $fieldAssociation->getGoogleField()];
                switch ($fieldAssociation->getAssociationType()) {
                    case GoogleFieldAssociationController::ASSO_TYPE_FIXED_VALUE:
                        $custom_field['FIELD_VALUE'] = $fieldAssociation->getFixedValue();
                        $found = true;
                        break;
                    case GoogleFieldAssociationController::ASSO_TYPE_RELATED_TO_THELIA_ATTRIBUTE:
                        $id_attribute = $fieldAssociation->getIdRelatedAttribute();
                        if (!array_key_exists($id_attribute, $attributes_array)) {
                            $attributes_array[$id_attribute] = $this->getArrayAttributesConcatValues($feed->getLang()->getLocale(), $id_attribute);
                        }
                        if ($found = array_key_exists($pse['ID'], $attributes_array[$id_attribute])) {
                            $custom_field['FIELD_VALUE'] = $attributes_array[$id_attribute][$pse['ID']];
                        }
                        break;
                    case GoogleFieldAssociationController::ASSO_TYPE_RELATED_TO_THELIA_FEATURE:
                        $id_feature = $fieldAssociation->getIdRelatedFeature();
                        if (!array_key_exists($id_feature, $features_array)) {
                            $features_array[$id_feature] = $this->getArrayFeaturesConcatValues($feed->getLang()->getLocale(), $id_feature);
                        }
                        if ($found = array_key_exists($pse['ID_PRODUCT'], $features_array[$id_feature])) {
                            $custom_field['FIELD_VALUE'] = $features_array[$id_feature][$pse['ID_PRODUCT']];
                        }
                        break;
                }
                if ($found) {
                    $custom_field_array[] = $custom_field;
                }
            }
            $pse['CUSTOM_FIELD_ARRAY'] = $custom_field_array;
        }
    }

    /**
     * @param GoogleshoppingxmlFeed $feed
     * @param array $pse_array
     */
    protected function injectAttributesInTitle(&$pse_array, $feed)
    {
        $attributes_concat_array = $this->getArrayAttributesConcatValues($feed->getLang()->getLocale(), null, ' - ');
        foreach ($pse_array as &$pse) {
            if (array_key_exists($pse['ID'], $attributes_concat_array)) {
                $pse['TITLE'] .= ' - ' . $attributes_concat_array[$pse['ID']];
            }
        }
    }


    /**
     * @param array $pse_array
     */
    protected function injectImages(&$pse_array)
    {
        foreach ($pse_array as &$pse) {
            if ($pse['IMAGE_NAME'] != null) {
                $imageEvent = $this->createImageEvent($pse['IMAGE_NAME'], 'product');
                $this->dispatch(TheliaEvents::IMAGE_PROCESS, $imageEvent);
                $pse['IMAGE_PATH'] = $imageEvent->getFileUrl();
            } else {
                $pse['IMAGE_PATH'] = null;
            }
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
        $con = Propel::getConnection();

        $sql = 'SELECT attribute_combination.product_sale_elements_id AS PSE_ID, GROUP_CONCAT(attribute_av_i18n.title SEPARATOR \''.$separator.'\') AS CONCAT FROM attribute_combination
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

        $shippingInfoArray = $this->getShippings($feed->getCountry(), $feed->getCurrency());

        foreach ($shippingInfoArray as $moduleTitle => $postagePrice) {
            $shippingItem = [];
            $shippingItem['country_code'] = $feed->getCountry()->getIsoalpha2();
            $shippingItem['service'] = $moduleTitle;
            $shippingItem['price'] = $postagePrice;
            $shippingItem['currency_id'] = $feed->getCurrencyId();
            $resultArray[] = $shippingItem;
        }

        return $resultArray;
    }

    /**
     * @param Country $country
     * @param Currency $currency
     * @return array
     */
    protected function getShippings($country, $currency)
    {
        $search = ModuleQuery::create()
            ->filterByActivate(1)
            ->filterByType(BaseModule::DELIVERY_MODULE_TYPE, Criteria::EQUAL)
            ->find();

        $deliveries = array();

        /** @var Module $deliveryModule */
        foreach ($search as $deliveryModule) {
            $areaDeliveryModule = AreaDeliveryModuleQuery::create()
                ->findByCountryAndModule($country, $deliveryModule);

            if (null === $areaDeliveryModule) {
                continue;
            }

            $moduleInstance = $deliveryModule->getDeliveryModuleInstance($this->container);

            if ($moduleInstance->isValidDelivery($country)) {
                $postage = OrderPostage::loadFromPostage($moduleInstance->getPostage($country));
                $price = $postage->getAmount() * $currency->getRate();

                $deliveries[$deliveryModule->getTitle()] = $price;
            }
        }

        return $deliveries;
    }
}
