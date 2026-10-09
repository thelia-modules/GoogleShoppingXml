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
in place, is written in the feed log, and the command exits with code 1. Two generations of the same feed never
overlap (lock file per feed, taken by the command and by the back office alike).

Rules, in this order: a combination of a product offline, excluded in the back office (Modules tab of the
product), or without stock when the module leaves those out, is skipped; listeners of `FeedItemEvent` may then
leave an item out or change its fields; an item missing a title, description, link, image, price or brand, or
whose EAN is refused by the EAN rule, is logged as an error and skipped.

Settings of the feed content (Advanced configuration tab): leave out combinations without stock, features read
for the color (three values at most, a `#rrggbb` code removed), the gender and the material, attributes read for
the size (no `g:size` when empty), product subtitle sent as a `product_detail` (labels translated in the
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

## Upgrade notes (4.0 to 4.1)

- Links: `?variant=<id>` is replaced by `?ref=<reference of the combination>`, the parameter Flexy reads. A listener
  of `FeedItemEvent` can set another link (`getProductUrl()` gives the address of the product on the feed domain).
- `googleshopping:generateXML` without `--feed` now generates every feed (4.0: the first one only). `--feed` takes
  the label or the id.
- Items missing a title, description, link, image, price or brand, or with an EAN refused by the EAN rule, are left
  out and logged (4.0 wrote them as they were). Check the Log tab after the first generation.
- A feed with no item, an unknown feed or any error leaves the previous file and exits with code 1: plug the exit
  code into the supervision of the scheduled task.
- The back-office generation is a POST with the session token, reserved to administrators who may update the module
  (route `googleshoppingxml.generatefeedxml`, now under `/admin`).
- The store name, the store description and the address of the shop (or of the language, with one domain per
  language) are now required: a feed whose language has none of them fails with an error instead of being written
  with an empty channel (`store_name`, `store_description` in the configuration variables, optionally suffixed
  with the locale, e.g. `store_name_de_DE`).
- `g:size`: only the attributes listed in the size setting are sent; with an empty setting no `g:size` is written
  (4.0 sent the value of every attribute of the combination as the size).
- `Service\Provider\ProductProvider`, `Service\Provider\SQLQueryService`, `Service\XmlGenerator` and
  `Service\GoogleModel\GoogleProductModel` are no longer used by the module and will be removed in the next major;
  the "Enable SQL 8 optimisations" switch has no effect any more.
- New table `googleshoppingxml_product_excluded` (`Config/update/4.1.0.sql`), exposed on the admin API of the
  combinations (`GoogleShoppingXmlProductExcluded.isExcluded`).
