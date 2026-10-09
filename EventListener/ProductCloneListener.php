<?php

declare(strict_types=1);

namespace GoogleShoppingXml\EventListener;

use GoogleShoppingXml\Service\ExclusionRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Product\ProductCloneEvent;
use Thelia\Core\Event\TheliaEvents;

/**
 * A cloned product is kept out of the Google Shopping feeds exactly where its original is. It runs after the
 * clone made by the core (priority 128), which has created the combinations.
 */
final readonly class ProductCloneListener implements EventSubscriberInterface
{
    public function __construct(private ExclusionRepository $exclusionRepository)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [TheliaEvents::PRODUCT_CLONE => ['copyExclusions', 100]];
    }

    public function copyExclusions(ProductCloneEvent $event): void
    {
        $this->exclusionRepository->copyToClonedProduct(
            (int) $event->getOriginalProduct()->getId(),
            (int) $event->getClonedProduct()->getId(),
        );
    }
}
