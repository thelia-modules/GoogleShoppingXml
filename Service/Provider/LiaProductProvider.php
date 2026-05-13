<?php

namespace GoogleShoppingXml\Service\Provider;

use Generator;
use GoogleShoppingXml\Model\GoogleshoppingxmlFeed;
use GoogleShoppingXml\Service\GoogleModel\GoogleLiaProductModel;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Model\TaxRuleQuery;
use Thelia\TaxEngine\Calculator;
use Thelia\Tools\MoneyFormat;
use Thelia\Tools\URL;

class LiaProductProvider
{
    protected $requestStack;
    protected $liaSqlQueryService;

    public function __construct(
        LiaSQLQueryService $liaSqlQueryService,
        RequestStack       $requestStack
    )
    {
        $this->liaSqlQueryService = $liaSqlQueryService;
        $this->requestStack = $requestStack;
    }

    /**
     * @param GoogleshoppingxmlFeed $feed
     * @return array
     * @throws PropelException
     */
    public function getContent(GoogleshoppingxmlFeed $feed)
    {
        $moneyFormat = MoneyFormat::getInstance($this->requestStack->getCurrentRequest());

        $store_name = \Thelia\Model\ConfigQuery::getStoreName();
        $store_description = \Thelia\Model\ConfigQuery::getStoreDescription();

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
        $taxCalculator = new Calculator();
        $taxeRules = $this->getTaxeRules();

        $resultStatement = $this->liaSqlQueryService->getPses($feed->getCurrencyId());

        $skippedPseIds = [];

        while ($row = $resultStatement->fetch(\PDO::FETCH_ASSOC)) {
            if (!isset($taxeRules[$row['TAX_RULE_ID']])) {
                $skippedPseIds[] = $row['id'];
                continue;
            }

            $taxCalculator->loadTaxRuleWithoutProduct($taxeRules[$row['TAX_RULE_ID']], $feed->getCountry());

            $quantity = (int) $row['quantity'];

            // Google recommends: >=3 in_stock, 1-2 limited_availability, 0 out_of_stock
            if ($quantity >= 3) {
                $availability = 'in_stock';
            } elseif ($quantity > 0) {
                $availability = 'limited_availability';
            } else {
                $availability = 'out_of_stock';
            }

            $price = $row['promo'] ? $row['promo_price'] : $row['price'];

            // TODO: When per-store pricing is implemented, use row['store_price'] instead
            // of the global row['price'] fetched from product_price.
            yield (new GoogleLiaProductModel(
                $taxCalculator,
                $moneyFormat,
                $feed->getCurrency()
            ))->build([
                'id' => $row['id'],
                'store_code' => $row['store_code'],
                'availability' => $availability,
                'price' => $price,
                'quantity' => $quantity,
            ]);
        }

        if (!empty($skippedPseIds)) {
            \Thelia\Log\Tlog::getInstance()->warning(sprintf(
                'LIA feed: %d PSE(s) skipped (missing tax rule): IDs %s',
                count($skippedPseIds),
                implode(', ', $skippedPseIds)
            ));
        }
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
