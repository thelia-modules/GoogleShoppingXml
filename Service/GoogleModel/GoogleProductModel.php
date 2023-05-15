<?php

namespace GoogleShoppingXml\Service\GoogleModel;

use GoogleShoppingXml\GoogleShoppingXml;
use GoogleShoppingXml\Service\ImageService;
use GoogleShoppingXml\Tools\GtinChecker;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Model\Currency;
use Thelia\TaxEngine\Calculator;
use Thelia\Tools\MoneyFormat;
use Thelia\Tools\URL;

class GoogleProductModel
{
    const EAN_RULE_ALL = "all";
    const EAN_RULE_CHECK_STRICT = "check_strict";
    const EAN_RULE_NONE = "none";
    const DEFAULT_EAN_RULE = self::EAN_RULE_CHECK_STRICT;

    /** @var int */
    protected $id;

    /** @var string */
    protected $title;

    /** @var string */
    protected $description;

    /** @var string */
    protected $link;

    /** @var string */
    protected $image_link;

    /** @var string */
    protected $availability;

    /** @var float */
    protected $price;

    /** @var string */
    protected $brand;

    /** @var string */
    protected $gtin;

    /** @var string */
    protected $identifier_exists;

    /** @var string */
    protected $item_group_id;

    /** @var string */
    protected $google_product_category;

    /** @var string */
    protected $product_type;

    /** @var string */
    protected $condition;

    /** @var array */
    protected $shipping;

    /** @var Currency */
    private $currency;

    /** @var Calculator */
    private $taxCalculator;

    /** @var array */
    private $extraFields;

    /** @var string */
    private $suffix;

    public function __construct(
        Calculator  $taxCalculator,
        MoneyFormat $moneyFormat,
        array       $shipping,
        Currency    $currency,
        array       $extraFields,
        string      $suffix = ''
    )
    {
        $this->moneyFormat = $moneyFormat;
        $this->taxCalculator = $taxCalculator;
        $this->shipping = $shipping;
        $this->currency = $currency;
        $this->suffix = $suffix;
        $this->extraFields = $extraFields;
    }

    public function build(array $data): GoogleProductModel
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
     * @param $ean
     * @return $this
     */
    public function setGtin($ean): GoogleProductModel
    {
        $eanRule = GoogleShoppingXml::getConfigValue("ean_rule", self::DEFAULT_EAN_RULE);

        $this->identifier_exists = "no";
        $this->gtin = null;

        if (!$ean || $eanRule === self::EAN_RULE_NONE) {
            return $this;
        }

        if ($eanRule === self::EAN_RULE_ALL || (new GtinChecker())->isValidGtin($ean)) {
            $this->identifier_exists = "yes";
            $this->gtin = $ean;
        }

        return $this;
    }

    /**
     * @param string|null $identifier_exists
     * @return GoogleProductModel
     */
    public function setIdentifierExists(?string $identifier_exists): GoogleProductModel
    {
        $this->identifier_exists = $identifier_exists;
        return $this;
    }

    /**
     * @param int $id
     * @return GoogleProductModel
     */
    public function setId(int $id): GoogleProductModel
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @param string $title
     * @return GoogleProductModel
     */
    public function setTitle(string $title): GoogleProductModel
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @param string $description
     * @return GoogleProductModel
     */
    public function setDescription(string $description): GoogleProductModel
    {
        $this->description = htmlspecialchars(html_entity_decode(trim(strip_tags($description))), ENT_XML1);;
        return $this;
    }

    /**
     * @param string $availability
     * @return GoogleProductModel
     */
    public function setAvailability(string $availability): GoogleProductModel
    {
        $this->availability = $availability;
        return $this;
    }

    /**
     * @param string $link
     * @return GoogleProductModel
     */
    public function setLink(string $link): GoogleProductModel
    {
        $this->link = $link;
        return $this;
    }

    /**
     * @param float $price
     * @return GoogleProductModel
     */
    public function setPrice(float $price): GoogleProductModel
    {
        $this->price = $this->moneyFormat->format(
            $this->taxCalculator->getTaxedPrice($price),
            null,
            ',',
            null,
            $this->currency->getCode()
        );
        return $this;
    }

    /**
     * @param string $item_group_id
     * @return GoogleProductModel
     */
    public function setItemGroupId(string $item_group_id): GoogleProductModel
    {
        $this->item_group_id = $item_group_id;
        return $this;
    }

    /**
     * @param string $brand
     * @return GoogleProductModel
     */
    public function setBrand(string $brand): GoogleProductModel
    {
        if (GoogleShoppingXml::getConfigValue("brand_rule", 0)) {
            $this->brand = $brand;
            return $this;
        }

        $this->brand = null;
        return $this;
    }

    /**
     * @param string $google_product_category
     * @return GoogleProductModel
     */
    public function setGoogleProductCategory(string $google_product_category): GoogleProductModel
    {
        $this->google_product_category = $google_product_category;
        return $this;
    }

    /**
     * @param string|null $condition
     * @return GoogleProductModel
     */
    public function setCondition($condition = null): GoogleProductModel
    {
        $this->condition = $condition ?? 'new';
        return $this;
    }

    /**
     * @param string $product_type
     * @return GoogleProductModel
     */
    public function setProductType(string $product_type): GoogleProductModel
    {
        $this->product_type = $product_type;
        return $this;
    }

    /**
     * @param array $shipping
     * @return GoogleProductModel
     */
    public function setshipping(array $shipping): GoogleProductModel
    {
        $this->shipping = $shipping;
        return $this;
    }

    /**
     * @param string $imageId
     */
    public function setImageLink(string $imageId): void
    {
        $this->image_link = URL::getInstance()->absoluteUrl("image-library/productImage/$imageId/full");
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
        return $this->extraFields;
    }
}