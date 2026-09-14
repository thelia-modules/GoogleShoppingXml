# Google Shopping Xml

This module allows you to export your catalog to Google Shopping through XML feeds.

## Installation

### Manually

* Copy the module into ```<thelia_root>/local/modules/``` directory and be sure that the name of the module is GoogleShoppingXml.
* Activate it in your thelia administration panel

### Composer

Add it in your main thelia composer.json file

```
composer require thelia/google-shopping-xml-module ~1.2.0
```

## Usage

See the module configuration in the back-office for explanations about how to use it.

## Events

NEW ! (>1.2.0)

You can now add your own fields by events like this :
```
class GoogleShoppingXmlListener extends BaseAction implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return array(
            AdditionalFieldEvent::ADD_FIELD_EVENT => ['addMyField', 64]
        );
    }

    public function addMyField(AdditionalFieldEvent $event)
    {
        $pseId = $event->getProductSaleElementsId();
        
        $event->addField('custom_label_0', "MY VALUE");
    }

}
```

## Shipping (>= 3.1.0)

A feed writes one `<g:shipping>` per country, carrying the **cheapest** delivery module that
serves it. Google accepts at most 100 of them per item, which is why only one carrier per
country is published rather than all of them.

### Choosing the countries

Each feed carries its own selection, edited in the *Feeds* tab. Only countries served by an
active delivery module are offered. Leaving the selection empty covers them all.

The `country_id` of a feed is a separate notion, unchanged: it is the country the taxes are
computed for.

### How the prices are worked out

`DeliveryModuleInterface::getPostage()` takes neither a product nor a weight - every carrier
reads the weight off the cart held in session. A feed is generated outside any checkout, with
an empty cart, so that path returns the lowest tier of the price grid for every product alike.

So the module calls the weight aware method carriers expose (`getOrderPostage()`, or
`getMinPostage()` for Chronopost) and falls back to `getPostage()` for a carrier that has
neither. Prices are computed once per generation for a handful of weight brackets - 0.25, 0.5,
1, 2, 5 and 10 kg - and each product reads the bracket its weight falls into, rounded up so a
quoted price is never short of what the carrier charges.

The cart amount is left at zero, so free shipping thresholds never apply: a threshold met by a
fictional cart would advertise free delivery on every item of the catalogue.

### Products without a weight

A weight of zero means one of two things, and the `virtual` flag of the product tells them
apart. A virtual product ships free everywhere. A physical product whose weight was never
filled in is priced on the lowest bracket rather than given free delivery.

### Countries with no rate

A country no carrier quotes is left out of the item rather than given an invented price. Worth
checking after a carrier is deactivated: the countries it alone served silently disappear from
the feed.

A country can be attached to its delivery area through one of its states rather than through the
country itself - the United States are, on a stock Thelia catalogue. Thelia's own
`findByCountryAndModule()` only reads the rows carrying no state, so the module reads
`country_area` directly instead; otherwise a country a carrier really serves would carry a
shipping rate on downloads and none at all on parcels.

### Legacy generation

`googleshopping:generateXML <feed> false` shares one shipping block across every item, so it
quotes all products at the lowest bracket. The optimised generation, which the back office and
the command use by default, prices each product on its own weight.
