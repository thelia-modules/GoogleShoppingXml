<?php

namespace GoogleShoppingXml\Service\Provider;

use PDO;
use Generator;
use GoogleShoppingXml\Model\GoogleshoppingxmlFeed;
use GoogleShoppingXml\Service\GoogleModel\GoogleProductModel;
use GoogleShoppingXml\Service\ShippingService;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Model\ConfigQuery;
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

        /** @var  $resultStatement */
        $resultStatement = $this->sqlQueryService->getPses($locale);
        $shipping = $this->shipingService->buildShippingArray($feed, $moneyFormat);

        while ($row = $resultStatement->fetch(PDO::FETCH_ASSOC)) {
            $taxCalculator->loadTaxRuleWithoutProduct($taxeRules[$row['TAX_RULE_ID']], $feed->getCountry());

            yield (new GoogleProductModel($taxCalculator, $moneyFormat, $shipping, $feed->getCurrency(), 'g:'))->build($row);
        }
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