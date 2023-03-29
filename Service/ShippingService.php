<?php

namespace GoogleShoppingXml\Service;

use GoogleShoppingXml\Model\GoogleshoppingxmlFeed;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\Model\AreaDeliveryModuleQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderPostage;
use Thelia\Module\BaseModule;
use Thelia\Tools\MoneyFormat;

class ShippingService
{
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @param GoogleshoppingxmlFeed $feed
     * @param MoneyFormat $moneyFormat
     * @return array
     * @throws PropelException
     */
    public function buildShippingArray(GoogleshoppingxmlFeed $feed, MoneyFormat $moneyFormat)
    {
        $resultArray = [];

        $shippingInfoArray = $this->getShippings($feed);

        foreach ($shippingInfoArray as $moduleTitle => $postagePrice) {
            $shippingItem['country_code'] = $feed->getCountry()->getIsoalpha2();
            $shippingItem['service'] = $moduleTitle;
            $shippingItem['price'] = $moneyFormat->format(
                $postagePrice,
                null,
                ',',
                null,
                $feed->getCurrency()->getCode());

            $resultArray[] = $shippingItem;
        }

        return $resultArray;
    }

    /**
     * @param GoogleshoppingxmlFeed $feed
     * @return array
     */
    protected function getShippings(GoogleshoppingxmlFeed $feed)
    {
        $country = $feed->getCountry();

        $search = ModuleQuery::create()
            ->filterByActivate(1)
            ->filterByType(BaseModule::DELIVERY_MODULE_TYPE, Criteria::EQUAL)
            ->find();

        $deliveries = array();

        /** @var Module $deliveryModule */
        foreach ($search as $deliveryModule) {
            $deliveryModule->setLocale($feed->getLang()->getLocale());

            $areaDeliveryModule = AreaDeliveryModuleQuery::create()
                ->findByCountryAndModule($country, $deliveryModule);

            if (null === $areaDeliveryModule) {
                continue;
            }

            $moduleInstance = $deliveryModule->getDeliveryModuleInstance($this->container);

            if ($moduleInstance->isValidDelivery($country)) {
                $postage = OrderPostage::loadFromPostage($moduleInstance->getPostage($country));
                $price = $postage->getAmount() * $feed->getCurrency()->getRate();

                $deliveries[$deliveryModule->getTitle()] = $price;
            }
        }

        return $deliveries;
    }
}