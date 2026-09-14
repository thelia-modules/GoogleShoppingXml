<?php

namespace GoogleShoppingXml\Service;

use GoogleShoppingXml\Model\GoogleshoppingxmlFeed;
use GoogleShoppingXml\Model\GoogleshoppingxmlFeedCountryQuery;
use GoogleShoppingXml\Model\GoogleshoppingxmlLogQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\Model\AreaDeliveryModuleQuery;
use Thelia\Model\Country;
use Thelia\Model\CountryAreaQuery;
use Thelia\Model\CountryQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;

/**
 * Builds, once per feed generation, the cheapest delivery price of every country for a handful
 * of weight brackets.
 *
 * Google allows at most 100 <g:shipping> per item, so a feed can carry one carrier per country
 * and no more. Pricing every product against every country would mean hundreds of thousands of
 * carrier calls; pricing a few brackets instead costs about a thousand, whatever the catalogue
 * size, and a product then just reads the bracket its weight falls into.
 */
class ShippingMatrixBuilder
{
    /**
     * Upper bounds in kg. A product is priced on the bound above its weight, never below, so a
     * quoted price is never short of what the carrier will actually charge.
     */
    public const WEIGHT_BRACKETS = [0.25, 0.5, 1.0, 2.0, 5.0, 10.0];

    /** Products flagged virtual in Thelia: downloads, no parcel to ship. */
    public const VIRTUAL_BRACKET = 'virtual';

    /** Google refuses, or silently truncates, an item carrying more than this many <g:shipping>. */
    private const MAX_SHIPPING_ENTRIES = 100;

    public function __construct(
        private ContainerInterface $container,
        private ShippingRateResolver $rateResolver,
    ) {
    }

    /**
     * Bracket key a product falls into.
     *
     * A weight of zero means two very different things: a genuine download, or a paper product
     * whose weight was never filled in. Only the virtual flag tells them apart, and getting it
     * wrong would either bill postage on a PDF or advertise free delivery on a real parcel.
     */
    public function bracketFor(?float $weight, bool $isVirtual): string
    {
        if ($isVirtual) {
            return self::VIRTUAL_BRACKET;
        }

        $weight ??= 0.0;

        foreach (self::WEIGHT_BRACKETS as $bracket) {
            if ($weight <= $bracket) {
                return (string) $bracket;
            }
        }

        return (string) self::WEIGHT_BRACKETS[\count(self::WEIGHT_BRACKETS) - 1];
    }

    /**
     * @return array<string, array<int, array{country: string, service: string, price: float}>>
     *         bracket key => one cheapest entry per country
     */
    public function build(GoogleshoppingxmlFeed $feed): array
    {
        $countries = $this->resolveCountries($feed);
        $deliveryModules = $this->activeDeliveryModules($feed);
        $locale = $feed->getLang()->getLocale();

        $matrix = [self::VIRTUAL_BRACKET => $this->buildVirtualRates($countries, $deliveryModules)];

        foreach (self::WEIGHT_BRACKETS as $bracket) {
            $matrix[(string) $bracket] = $this->cheapestPerCountry($countries, $deliveryModules, $bracket, $locale);
        }

        return $matrix;
    }

    /**
     * @param Country[] $countries
     * @param Module[]  $deliveryModules
     *
     * @return array<int, array{country: string, service: string, price: float}>
     */
    private function cheapestPerCountry(array $countries, array $deliveryModules, float $weight, string $locale): array
    {
        $rates = [];

        foreach ($countries as $country) {
            $cheapest = null;

            foreach ($deliveryModules as $deliveryModule) {
                if (!$this->deliversTo($deliveryModule, $country)) {
                    continue;
                }

                $price = $this->rateResolver->resolve(
                    $deliveryModule->getDeliveryModuleInstance($this->container),
                    $country,
                    $weight,
                    $locale
                );

                if (null === $price) {
                    continue;
                }

                if (null === $cheapest || $price < $cheapest['price']) {
                    $cheapest = ['service' => $this->serviceName($deliveryModule), 'price' => $price];
                }
            }

            // No carrier quotes this country at this weight: leave it out rather than publish a
            // price nobody would honour.
            if (null === $cheapest) {
                continue;
            }

            $rates[] = [
                'country' => $country->getIsoalpha2(),
                'service' => $cheapest['service'],
                'price' => $cheapest['price'],
            ];
        }

        return $rates;
    }

    /**
     * Downloads ship for free everywhere. The carriers are not asked: their price grids are
     * keyed on weight and a virtual product has none.
     *
     * @param Country[] $countries
     * @param Module[]  $deliveryModules
     *
     * @return array<int, array{country: string, service: string, price: float}>
     */
    private function buildVirtualRates(array $countries, array $deliveryModules): array
    {
        $service = null;

        foreach ($deliveryModules as $deliveryModule) {
            if ('VirtualProductDelivery' === $deliveryModule->getCode()) {
                $service = $this->serviceName($deliveryModule);
                break;
            }
        }

        // Only when the carrier of virtual products is off: nothing to advertise.
        if (null === $service) {
            return [];
        }

        $rates = [];

        foreach ($countries as $country) {
            $rates[] = [
                'country' => $country->getIsoalpha2(),
                'service' => $service,
                'price' => 0.0,
            ];
        }

        return $rates;
    }

    /**
     * Carriers are not translated in every language a feed may run in, and an empty <g:service>
     * tells a reader nothing. The module code is a poor label, but it is always there.
     */
    private function serviceName(Module $deliveryModule): string
    {
        $title = $deliveryModule->getTitle();

        return '' !== (string) $title ? (string) $title : $deliveryModule->getCode();
    }

    /**
     * A country may carry its delivery area on one of its states rather than on the country
     * itself - the United States do. findByCountryAndModule() falls back to the rows with no
     * state, so it reports a carrier that does serve the country as not serving it, and the
     * country then silently drops out of every physical item of the feed.
     */
    private function deliversTo(Module $deliveryModule, Country $country): bool
    {
        $areaIds = CountryAreaQuery::create()
            ->filterByCountryId($country->getId())
            ->select('AreaId')
            ->find()
            ->getData();

        if ([] === $areaIds) {
            return false;
        }

        return null !== AreaDeliveryModuleQuery::create()
            ->filterByAreaId($areaIds, Criteria::IN)
            ->filterByDeliveryModuleId($deliveryModule->getId())
            ->findOne();
    }

    /**
     * Titles are read off these modules, so the locale has to be set before the feed reads them,
     * or an English feed comes out carrying French carrier names.
     *
     * @return Module[]
     */
    private function activeDeliveryModules(GoogleshoppingxmlFeed $feed): array
    {
        $modules = ModuleQuery::create()
            ->filterByActivate(1)
            ->filterByType(BaseModule::DELIVERY_MODULE_TYPE, Criteria::EQUAL)
            ->find();

        foreach ($modules as $module) {
            $module->setLocale($feed->getLang()->getLocale());
        }

        return iterator_to_array($modules);
    }

    /**
     * Countries picked on the feed, or every country a live carrier serves when none were picked.
     *
     * @return Country[]
     */
    private function resolveCountries(GoogleshoppingxmlFeed $feed): array
    {
        $countryIds = GoogleshoppingxmlFeedCountryQuery::create()
            ->filterByFeedId($feed->getId())
            ->select('CountryId')
            ->find()
            ->getData();

        if ([] === $countryIds) {
            $countryIds = self::servedCountryIds();
        }

        $countries = CountryQuery::create()
            ->filterById($countryIds, Criteria::IN)
            ->filterByVisible(1)
            ->find()
            ->getData();

        if (\count($countries) <= self::MAX_SHIPPING_ENTRIES) {
            return $countries;
        }

        // One <g:shipping> per country, so more countries than the cap means Google rejects the
        // item. Truncating keeps the feed valid; the log says which countries fell off.
        GoogleshoppingxmlLogQuery::create()->logWarning(
            $feed,
            null,
            sprintf(
                '%d countries selected for shipping, Google accepts %d per item.',
                \count($countries),
                self::MAX_SHIPPING_ENTRIES
            ),
            'Only the first countries are kept. Narrow the country selection of this feed.'
        );

        return \array_slice($countries, 0, self::MAX_SHIPPING_ENTRIES);
    }

    /**
     * Every country a live carrier serves. Static so the back office can offer the same list
     * without pulling the whole service in.
     *
     * @return int[]
     */
    public static function servedCountryIds(): array
    {
        $moduleIds = ModuleQuery::create()
            ->filterByActivate(1)
            ->filterByType(BaseModule::DELIVERY_MODULE_TYPE, Criteria::EQUAL)
            ->select('Id')
            ->find()
            ->getData();

        $areaIds = AreaDeliveryModuleQuery::create()
            ->filterByDeliveryModuleId($moduleIds, Criteria::IN)
            ->select('AreaId')
            ->distinct()
            ->find()
            ->getData();

        return CountryAreaQuery::create()
            ->filterByAreaId($areaIds, Criteria::IN)
            ->select('CountryId')
            ->distinct()
            ->find()
            ->getData();
    }
}
