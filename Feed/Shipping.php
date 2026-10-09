<?php

declare(strict_types=1);

namespace GoogleShoppingXml\Feed;

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\Model\AreaDeliveryModuleQuery;
use Thelia\Model\Country;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderPostage;
use Thelia\Module\BaseModule;

/**
 * The shipping costs of an item, one <g:shipping> per delivery module that delivers the country of the
 * feed. A module that can price a parcel by its weight and amount (a static getPostageAmount($areaId,
 * $weight, $amount), as the Colissimo modules) gives the cost of the item alone; another one gives its
 * usual postage. A module that cannot answer outside a cart (exception) is left out.
 */
final class Shipping
{
    /** @var list<array{title: string, area_id: int, priced_by_item: bool, module: object, fixed: ?float}> */
    private array $services = [];

    public function __construct(
        ContainerInterface $container,
        private readonly Country $country,
        string $locale,
        private readonly float $currencyRate,
        private readonly PriceFormatter $priceFormatter,
    ) {
        $modules = ModuleQuery::create()
            ->filterByActivate(1)
            ->filterByType(BaseModule::DELIVERY_MODULE_TYPE, Criteria::EQUAL)
            ->orderById()
            ->find();

        foreach ($modules as $module) {
            $area = AreaDeliveryModuleQuery::create()->findByCountryAndModule($country, $module);
            if (null === $area) {
                continue;
            }

            try {
                $instance = $module->getDeliveryModuleInstance($container);
                if (!$instance->isValidDelivery($country)) {
                    continue;
                }
                $pricedByItem = method_exists($instance, 'getPostageAmount');
                $fixed = $pricedByItem ? null : (float) OrderPostage::loadFromPostage($instance->getPostage($country))->getAmount();
            } catch (\Throwable) {
                continue;
            }

            $module->setLocale($locale);
            $this->services[] = [
                'title' => (string) $module->getTitle(),
                'area_id' => (int) $area->getAreaId(),
                'priced_by_item' => $pricedByItem,
                'module' => $instance,
                'fixed' => $fixed,
            ];
        }
    }

    /**
     * @return list<array<string, string>>
     */
    public function of(float $weight, float $untaxedPrice): array
    {
        $shipping = [];
        foreach ($this->services as $service) {
            $amount = $service['fixed'];
            if ($service['priced_by_item']) {
                try {
                    $amount = $service['module']::getPostageAmount($service['area_id'], $weight, $untaxedPrice);
                } catch (\Throwable) {
                    $amount = null;
                }
            }
            if (null === $amount || false === $amount) {
                continue;
            }

            $shipping[] = [
                'country' => (string) $this->country->getIsoalpha2(),
                'service' => $service['title'],
                'price' => $this->priceFormatter->format((float) $amount * $this->currencyRate),
            ];
        }

        return $shipping;
    }
}
