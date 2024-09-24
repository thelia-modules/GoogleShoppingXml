<?php

namespace GoogleShoppingXml\Service\Provider;

use GoogleShoppingXml\GoogleShoppingXml;
use OpenApi\Model\Api\Product;
use PDO;
use Generator;
use GoogleShoppingXml\Model\GoogleshoppingxmlFeed;
use GoogleShoppingXml\Service\GoogleModel\GoogleProductModel;
use GoogleShoppingXml\Service\ShippingService;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Template\Loop\ProductSaleElementsImage;
use Thelia\Model\Base\ProductQuery;
use Thelia\Model\Category;
use Thelia\Model\CategoryQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ProductImageQuery;
use Thelia\Model\ProductSaleElementsProductImageQuery;
use Thelia\Model\TaxRuleQuery;
use Thelia\TaxEngine\Calculator;
use Thelia\Tools\MoneyFormat;
use Thelia\Tools\URL;

class ProductProvider
{
    public function __construct(
        protected SQLQueryService $sqlQueryService,
        protected ShippingService $shipingService,
        protected RequestStack    $requestStack
    )
    {
    }

    /**
     * @throws PropelException
     */
    public function getContent($feed)
    {
        $moneyFormat = MoneyFormat::getInstance($this->requestStack->getCurrentRequest());

        $store_name = ConfigQuery::getStoreName();
        $store_description = ConfigQuery::getStoreDescription();

        return [
            'title' => $store_name,
            'link' => URL::getInstance()->getIndexPage(),
            'description' => $store_description,
            'item' => $this->getDataGenerator($feed, $moneyFormat)
        ];
    }

    /**
     * @param GoogleshoppingxmlFeed $feed
     * @param MoneyFormat $moneyFormat
     * @return Generator
     * @throws PropelException
     */
    public function getDataGenerator(GoogleshoppingxmlFeed $feed, MoneyFormat $moneyFormat): Generator
    {
        $locale = $feed->getLang()->getLocale();

        $taxCalculator = new Calculator();
        $taxeRules = $this->getTaxeRules();
        $shipping = $this->shipingService->buildShippingArray($feed, $moneyFormat);

        $optimisation = GoogleShoppingXml::getConfigValue(GoogleShoppingXml::ENABLE_SQL_8_COMPATIBILITY);

        /** @var  $resultStatement */

        if ($optimisation) {
            $resultStatement = $this->sqlQueryService->getPsesWithSqlOptimisation($locale);
        } else {
            $resultStatement = $this->sqlQueryService->getPses($locale);
        }

        while ($row = $resultStatement->fetch(PDO::FETCH_ASSOC)) {
            $taxCalculator->loadTaxRuleWithoutProduct($taxeRules[$row['TAX_RULE_ID']], $feed->getCountry());

            $row['title'] = $row['product_title'];

            if (null !== $row['attrib_title']) {
                $row['title'] .= ' - '.$row['attrib_title'];
            }

            if (!$optimisation) {

                $pseImage = ProductSaleElementsProductImageQuery::create()
                    ->filterByProductSaleElementsId($row['id'])
                    ->findOne();

                $productImage = ProductImageQuery::create()
                    ->useProductQuery()
                    ->useProductSaleElementsQuery()
                    ->filterById($row['id'])
                    ->endUse()
                    ->endUse()
                    ->orderByPosition()
                    ->findOne();

                $row['image_link'] = $pseImage ? $pseImage->getProductImageId() : $productImage?->getId();

                $path = $this->getCategories(CategoryQuery::create()->findPk($row['default_category_id']), $locale);

                $row['product_type'] = implode(' > ', $path);
            }

            yield (new GoogleProductModel($taxCalculator, $moneyFormat, $shipping, $feed->getCurrency(), 'g:'))->build($row);
        }
    }

    private function getCategories(Category $category, $locale)
    {
        if ($category->getParent() !== 0){
            $parentCategory = CategoryQuery::create()->findPk($category->getParent());
            $path = $this->getCategories($parentCategory, $locale);
        }

        $category->setLocale($locale);
        $path[] = $category->getTitle();

        return $path;
    }

    /**
     * @return array
     */
    private function getTaxeRules(): array
    {
        $taxRules = [];

        foreach (TaxRuleQuery::create()->find() as $taxRule) {
            $taxRules[$taxRule->getId()] = $taxRule;
        }

        return $taxRules;
    }
}