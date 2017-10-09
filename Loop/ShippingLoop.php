<?php

namespace GoogleShoppingXml\Loop;

use GoogleShoppingXml\Model\GoogleshoppingxmlFeed;
use GoogleShoppingXml\Model\GoogleshoppingxmlFeedCountry;
use GoogleShoppingXml\Model\GoogleshoppingxmlFeedCountryQuery;
use GoogleShoppingXml\Model\GoogleshoppingxmlFeedQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Template\Element\ArraySearchLoopInterface;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\AreaDeliveryModuleQuery;
use Thelia\Model\Country;
use Thelia\Model\Currency;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderPostage;
use Thelia\Module\BaseModule;

class ShippingLoop extends BaseLoop implements ArraySearchLoopInterface
{
    public function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('feed_id')
        );
    }

    public function buildArray()
    {
        $resultArray = [];

        $feed_id = $this->getFeedId();

        /** @var GoogleshoppingxmlFeed $feed */
        $feed = GoogleshoppingxmlFeedQuery::create()->findOneById($feed_id);

        $feedCountryArray = GoogleshoppingxmlFeedCountryQuery::create()
            ->filterByFeedId($feed_id)
            ->find();

        /** @var GoogleshoppingxmlFeedCountry $feedCountry */
        foreach ($feedCountryArray as $feedCountry) {
            $shippingInfoArray = $this->getShippings($feedCountry->getCountry(), $feed->getCurrency());

            foreach ($shippingInfoArray as $moduleTitle => $postagePrice) {
                $shippingItem = [];
                $shippingItem['country'] = $feedCountry->getCountry();
                $shippingItem['service'] = $moduleTitle;
                $shippingItem['price'] = $postagePrice;
                $shippingItem['currency'] = $feed->getCurrency();
                $resultArray[] = $shippingItem;
            }
        }

        return $resultArray;
    }


    public function parseResults(LoopResult $loopResult)
    {
        /** @var array $data */
        foreach ($loopResult->getResultDataCollection() as $data) {
            $loopResultRow = new LoopResultRow();

            $loopResultRow->set("COUNTRY_CODE", $data['country']->getIsoalpha2());
            $loopResultRow->set("COUNTRY_ID", $data['country']->getId());
            $loopResultRow->set("SERVICE", $data['service']);
            $loopResultRow->set("PRICE", $data['price']);
            $loopResultRow->set("CURRENCY_ID", $data['currency']->getId());
            $loopResultRow->set("CURRENCY_CODE", $data['currency']->getCode());


            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
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
