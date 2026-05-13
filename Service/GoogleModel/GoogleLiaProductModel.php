<?php

namespace GoogleShoppingXml\Service\GoogleModel;

use Thelia\Model\Currency;
use Thelia\TaxEngine\Calculator;
use Thelia\Tools\MoneyFormat;

class GoogleLiaProductModel
{
    /** @var int */
    protected $id;

    /** @var string */
    protected $store_code;

    /** @var string */
    protected $availability;

    /** @var string */
    protected $price;

    /** @var int */
    protected $quantity;

    /** @var Currency */
    private $currency;

    /** @var Calculator */
    private $taxCalculator;

    /** @var MoneyFormat */
    private $moneyFormat;

    /** @var string */
    private $suffix;

    public function __construct(
        Calculator  $taxCalculator,
        MoneyFormat $moneyFormat,
        Currency    $currency,
        string      $suffix = 'g:'
    )
    {
        $this->taxCalculator = $taxCalculator;
        $this->moneyFormat = $moneyFormat;
        $this->currency = $currency;
        $this->suffix = $suffix;
    }

    public function build(array $data): GoogleLiaProductModel
    {
        foreach ($data as $key => $value) {
            $methodName = ucwords(str_replace('_', ' ', $key));
            $methodName = ucwords(str_replace(' ', '', $methodName));

            $setMethodName = "set$methodName";
            if (method_exists($this, $setMethodName)) {
                $this->$setMethodName($value);
            }
        }

        return $this;
    }

    /**
     * @param int $id
     * @return GoogleLiaProductModel
     */
    public function setId(int $id): GoogleLiaProductModel
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $store_code
     * @return GoogleLiaProductModel
     */
    public function setStoreCode(string $store_code): GoogleLiaProductModel
    {
        $this->store_code = $store_code;
        return $this;
    }

    /**
     * @param string $availability
     * @return GoogleLiaProductModel
     */
    public function setAvailability(string $availability): GoogleLiaProductModel
    {
        $this->availability = $availability;
        return $this;
    }

    /**
     * @param string $price
     * @return GoogleLiaProductModel
     */
    public function setPrice(string $price): GoogleLiaProductModel
    {
        $this->price = $this->moneyFormat->format(
            $this->taxCalculator->getTaxedPrice((float) $price),
            null,
            '.',
            null,
            $this->currency->getCode()
        );
        return $this;
    }

    /**
     * @param int $quantity
     * @return GoogleLiaProductModel
     */
    public function setQuantity(int $quantity): GoogleLiaProductModel
    {
        $this->quantity = $quantity;
        return $this;
    }

    /**
     * @return string
     */
    public function getSuffix()
    {
        return $this->suffix;
    }

    /**
     * @return array
     */
    public function getExtraFields(): array
    {
        return [];
    }
}
