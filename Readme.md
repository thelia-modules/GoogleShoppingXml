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

## Generation (4.1)

```
php bin/console googleshopping:generateXML                       # every feed
php bin/console googleshopping:generateXML --feed="Feed label"   # one feed, by label (quoted) or id
```

Each feed is generated in its own language: when the shop has one domain per language, its links and image
addresses are on the domain of that language. The file is written next to its final name
(`local/GoogleShoppingXML/<label>.xml`) and renamed once complete: a failed generation leaves the previous feed
in place, is written in the feed log, and the command exits with code 1. Two runs never overlap (lock file).

Rules, in this order: a combination of a product offline, excluded in the back office (Modules tab of the
product), or without stock when the module leaves those out, is skipped; listeners of `FeedItemEvent` may then
leave an item out or change its fields; an item missing a title, description, link, image, price or brand, or
whose EAN is refused by the EAN rule, is logged as an error and skipped.

Settings of the feed content (Advanced configuration tab): leave out combinations without stock, features read
for the color (three values at most, a `#rrggbb` code removed), the gender and the material, attributes read for
the size (every attribute when empty), product subtitle sent as a `product_detail` (labels translated in the
language of the feed), image filter set (`default`: the original image).

## FeedItemEvent (4.1)

Dispatched for every item about to be written (`GoogleShoppingXmlEvents::FEED_ITEM`):

```php
public static function getSubscribedEvents(): array
{
    return [GoogleShoppingXmlEvents::FEED_ITEM => ['applyRules', 64]];
}

public function applyRules(FeedItemEvent $event): void
{
    if ($this->isNotSold($event->getProductSaleElementsId())) {
        $event->exclude('Not sold any more'); // the reason is written in the feed log; null: counted only

        return;
    }

    $event->getItem()->set('custom_label_0', 'summer');                     // add or replace a field
    $event->getItem()->set('link', $event->getProductUrl().'?size=42');      // product URL on the feed domain
}
```

`AdditionalFieldEvent` is still dispatched for every item, before `FeedItemEvent`.
