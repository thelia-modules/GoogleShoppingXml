<?php

namespace GoogleShoppingXml\Service;

use Thelia\Model\Country;
use Thelia\Model\OrderPostage;
use Thelia\Module\Exception\DeliveryException;

/**
 * Resolves the postage price of one delivery module, for one country and one weight.
 *
 * DeliveryModuleInterface::getPostage() takes neither a product nor a weight: every
 * implementation reads the weight off the cart held in session. A feed is generated outside
 * any checkout, with an empty cart, so that path always returns the lowest tier of the price
 * grid - the same price for every product, whatever it weighs.
 *
 * Carriers expose the weight aware method that getPostage() itself delegates to once it has
 * read the cart, so we call that one directly rather than fake a cart in session.
 */
class ShippingRateResolver
{
    /**
     * Postage amount, or null when the module cannot deliver this country at this weight.
     */
    public function resolve(object $moduleInstance, Country $country, float $weight, string $locale): ?float
    {
        // Dpd Classic, Dpd Pickup, Predict. The cart amount stays at 0 so that free shipping
        // thresholds never trigger: a threshold met by a fictional cart would advertise free
        // delivery on every single item of the feed.
        if (method_exists($moduleInstance, 'getOrderPostage')) {
            return $this->amountOf(static fn () => $moduleInstance->getOrderPostage($country, $weight, $locale, 0));
        }

        if (method_exists($moduleInstance, 'getMinPostage')) {
            return $this->cheapestPerDeliveryType($moduleInstance, $country, $weight, $locale);
        }

        // Any other carrier: weight unaware, one price per country.
        return $this->amountOf(static fn () => $moduleInstance->getPostage($country));
    }

    /**
     * Chronopost prices a pickup point per delivery type and refuses to quote without one, so
     * we walk the activated types and keep the cheapest - the same thing its getPostage() does
     * when the checkout has not settled on a type yet.
     */
    private function cheapestPerDeliveryType(object $moduleInstance, Country $country, float $weight, string $locale): ?float
    {
        if (!method_exists($moduleInstance, 'getActivatedDeliveryTypes')) {
            return null;
        }

        $cheapest = null;

        foreach ($moduleInstance::getActivatedDeliveryTypes() as $deliveryType) {
            // The module raises a bare \Exception for an unsupported delivery code and wraps
            // everything else into a DeliveryException; it skips both in its own loop.
            try {
                $amount = $this->toAmount($moduleInstance->getMinPostage($country, $weight, 0, $deliveryType, $locale));
            } catch (\Exception) {
                continue;
            }

            if (null !== $amount && (null === $cheapest || $amount < $cheapest)) {
                $cheapest = $amount;
            }
        }

        return $cheapest;
    }

    private function amountOf(callable $call): ?float
    {
        // Raised when the weight runs past the last tier of the grid, or when the country is
        // not covered. Either way this module is out for this country and weight.
        try {
            return $this->toAmount($call());
        } catch (DeliveryException) {
            return null;
        }
    }

    private function toAmount(OrderPostage|float|int|null $postage): ?float
    {
        if (null === $postage) {
            return null;
        }

        return OrderPostage::loadFromPostage($postage)->getAmount();
    }
}
