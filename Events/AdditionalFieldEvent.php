<?php

namespace GoogleShoppingXml\Events;

use Thelia\Core\Event\ActionEvent;

class AdditionalFieldEvent extends ActionEvent
{
    const ADD_FIELD_EVENT = "add_field_event";

    /** @var int */
    protected $productSaleElementsId;

    /** @var array */
    protected $fields = [];

    public function __construct($productSaleElementsId)
    {
        $this->productSaleElementsId = $productSaleElementsId;
    }

    /**
     * @return int
     */
    public function getProductSaleElementsId()
    {
        return $this->productSaleElementsId;
    }

    /**
     * @return array
     */
    public function getFields()
    {
        return $this->fields;
    }

    public function addField($name, $value)
    {
        $this->fields[$name] = $value;
    }

    public function removeField($name)
    {
        unset($this->fields[$name]);
    }

}
