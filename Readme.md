# Google Shopping Xml

This module allows you to export your catalog to Google Shopping through XML feeds.

## Installation

### Manually

* Copy the module into ```<thelia_root>/local/modules/``` directory and be sure that the name of the module is GoogleShoppingXml.
* Activate it in your thelia administration panel

### Composer

Add it in your main thelia composer.json file

```
composer require thelia/google-shopping-xml-module:~1.2.0
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
