<?php

namespace GoogleShoppingXml\Service\Provider;

use GoogleShoppingXml\Model\GoogleshoppingxmlGoogleFieldAssociationQuery;
use GoogleShoppingXml\Model\Map\GoogleshoppingxmlGoogleFieldAssociationTableMap;
use PDO;
use Generator;
use GoogleShoppingXml\Model\GoogleshoppingxmlFeed;
use GoogleShoppingXml\Service\GoogleModel\GoogleProductModel;
use GoogleShoppingXml\Service\ShippingService;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\AttributeAvI18nTableMap;
use Thelia\Model\Map\AttributeI18nTableMap;
use Thelia\Model\TaxRuleQuery;
use Thelia\TaxEngine\Calculator;
use Thelia\Tools\MoneyFormat;
use Thelia\Tools\URL;

class ProductProvider
{
    protected $requestStack;
    protected $shipingService;
    protected $sqlQueryService;

    public function __construct(
        SQLQueryService $sqlQueryService,
        ShippingService $shipingService,
        RequestStack    $requestStack
    )
    {
        $this->sqlQueryService = $sqlQueryService;
        $this->shipingService = $shipingService;
        $this->requestStack = $requestStack;
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

        /** @var  $resultStatement */
        $resultStatement = $this->sqlQueryService->getPses($locale);
        $shipping = $this->shipingService->buildShippingArray($feed, $moneyFormat);

        while ($row = $resultStatement->fetch(PDO::FETCH_ASSOC)) {
            $taxCalculator->loadTaxRuleWithoutProduct($taxeRules[$row['TAX_RULE_ID']], $feed->getCountry());

            $extraStaticFields = $this->getExtraStaticFields();

            $extraAttributeFields = $this->getExtraAttributeFields($row['id'], $locale);

            yield (new GoogleProductModel(
                $taxCalculator,
                $moneyFormat,
                $shipping,
                $feed->getCurrency(),
                array_merge($extraStaticFields, $extraAttributeFields),
                'g:')
            )->build($row);
        }
    }

    private function getExtraStaticFields()
    {
        return GoogleshoppingxmlGoogleFieldAssociationQuery::create()
            ->select(['value', 'google_field'])
            ->addAsColumn('value', GoogleshoppingxmlGoogleFieldAssociationTableMap::COL_FIXED_VALUE)
            ->addAsColumn('google_field', GoogleshoppingxmlGoogleFieldAssociationTableMap::COL_GOOGLE_FIELD)
            ->filterByFixedValue(null, Criteria::ISNOTNULL)
            ->find()
            ->toArray();
    }

    private function getExtraAttributeFields(int $pseId, string $locale ='en_US')
    {
        return GoogleshoppingxmlGoogleFieldAssociationQuery::create()
            ->select(['value', 'google_field'])
            ->addAsColumn('value', AttributeAvI18nTableMap::COL_TITLE)
            ->addAsColumn('google_field', GoogleshoppingxmlGoogleFieldAssociationTableMap::COL_GOOGLE_FIELD)
            ->useAttributeQuery()
                ->useAttributeCombinationQuery()
                    ->useAttributeAvQuery()
                        ->useAttributeAvI18nQuery()
                            ->filterByLocale($locale)
                        ->endUse()
                    ->endUse()
                    ->useProductSaleElementsQuery()
                        ->filterById($pseId)
                    ->endUse()
                ->endUse()
            ->endUse()
            ->find()
        ->toArray();
    }

    /**
     * @return array
     */
    private function getTaxeRules()
    {
        $taxRules = [];

        foreach (TaxRuleQuery::create()->find() as $taxRule) {
            $taxRules[$taxRule->getId()] = $taxRule;
        }

        return $taxRules;
    }
}