<?php

declare(strict_types=1);

namespace GoogleShoppingXml\Feed;

use Propel\Runtime\Propel;
use Thelia\Domain\Taxation\TaxEngine\TaxCalculatorFactoryInterface;
use Thelia\Domain\Taxation\TaxEngine\TaxCalculatorInterface;
use Thelia\Model\Country;
use Thelia\Model\ProductQuery;
use Thelia\Model\TaxRule;
use Thelia\Model\TaxRuleQuery;

/**
 * Adds the taxes of a country to a price. A tax rule is loaded once for the whole feed: only a rule
 * holding a tax that reads the product itself (an amount taken from a feature) is loaded per product.
 */
final class TaxedPrices
{
    /** @var array<int, TaxCalculatorInterface> by tax rule id */
    private array $calculatorsByTaxRule = [];

    /** @var array<int, bool> by tax rule id */
    private array $readsTheProduct = [];

    /** @var array<int, TaxCalculatorInterface> by product id */
    private array $calculatorsByProduct = [];

    public function __construct(
        private readonly TaxCalculatorFactoryInterface $taxCalculatorFactory,
        private readonly Country $country,
    ) {
    }

    public function taxedPrice(float $untaxedPrice, int $taxRuleId, int $productId): float
    {
        return (float) $this->calculator($taxRuleId, $productId)->getTaxedPrice($untaxedPrice);
    }

    private function calculator(int $taxRuleId, int $productId): TaxCalculatorInterface
    {
        if (!$this->readsTheProduct($taxRuleId)) {
            return $this->calculatorsByTaxRule[$taxRuleId] ??= $this->taxCalculatorFactory->createTaxCalculator()
                ->loadTaxRuleWithoutProduct($this->taxRule($taxRuleId), $this->country);
        }

        $product = ProductQuery::create()->findPk($productId) ?? throw new \LogicException(\sprintf('The product %d does not exist.', $productId));

        return $this->calculatorsByProduct[$productId] ??= $this->taxCalculatorFactory->createTaxCalculator()
            ->loadTaxRule($this->taxRule($taxRuleId), $this->country, $product);
    }

    private function taxRule(int $taxRuleId): TaxRule
    {
        return TaxRuleQuery::create()->findPk($taxRuleId) ?? throw new \LogicException(\sprintf('The tax rule %d does not exist.', $taxRuleId));
    }

    private function readsTheProduct(int $taxRuleId): bool
    {
        if (isset($this->readsTheProduct[$taxRuleId])) {
            return $this->readsTheProduct[$taxRuleId];
        }

        $statement = Propel::getConnection()->prepare(
            'SELECT tax.type FROM tax_rule_country AS link INNER JOIN tax ON tax.id = link.tax_id WHERE link.tax_rule_id = ?',
        );
        $statement->bindValue(1, $taxRuleId, \PDO::PARAM_INT);
        $statement->execute();

        $readsTheProduct = false;
        foreach ($statement->fetchAll(\PDO::FETCH_COLUMN) as $taxType) {
            $readsTheProduct = $readsTheProduct || str_contains((string) $taxType, 'Feature');
        }

        return $this->readsTheProduct[$taxRuleId] = $readsTheProduct;
    }
}
