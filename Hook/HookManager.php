<?php

namespace GoogleShoppingXml\Hook;

use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;

class HookManager extends BaseHook
{
    public static function getSubscribedHooks(): array
    {
        return [
            'main.head-css' => [
                ['type' => 'back', 'method' => 'onMainHeadCss'],
            ],
            'home.bottom' => [
                ['type' => 'back', 'method' => 'onHomeBottom'],
            ],
            'home.js' => [
                ['type' => 'back', 'method' => 'onHomeJs'],
            ],
        ];
    }

    public function onMainHeadCss(HookRenderEvent $event): void
    {
        // addCSS() relies on the current parser, which can be null in the Twig BO.
        // Emit the module styles from a dedicated fragment instead.
        $event->add($this->render('GoogleShoppingXml/main-head-css.html.twig'));
    }

    public function onHomeBottom(HookRenderEvent $event): void
    {
        $event->add($this->render('GoogleShoppingXml/home-bottom.html.twig'));
    }

    public function onHomeJs(HookRenderEvent $event): void
    {
        $event->add($this->render('GoogleShoppingXml/home-js.html.twig'));
    }
}
