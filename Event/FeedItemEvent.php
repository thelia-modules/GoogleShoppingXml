<?php

declare(strict_types=1);

namespace GoogleShoppingXml\Event;

use GoogleShoppingXml\Feed\FeedItem;
use GoogleShoppingXml\Feed\FeedRow;
use GoogleShoppingXml\Model\GoogleshoppingxmlFeed;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * One combination about to be written in a feed, once the rules of the module have kept it (visible
 * product, not excluded, in stock when asked). Listeners can:
 * - leave it out of this feed with exclude(); with a reason, the reason is written in the feed log;
 * - add, replace or remove a field through getItem().
 *
 * The required fields (title, description, link, image, price, brand) are checked after the listeners:
 * an item a listener leaves incomplete is logged as an error and skipped.
 */
class FeedItemEvent extends Event
{
    private bool $excluded = false;

    private ?string $exclusionReason = null;

    public function __construct(
        private readonly GoogleshoppingxmlFeed $feed,
        private readonly string $locale,
        private readonly FeedRow $row,
        private readonly FeedItem $item,
        private readonly string $productUrl,
    ) {
    }

    public function getFeed(): GoogleshoppingxmlFeed
    {
        return $this->feed;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getProductSaleElementsId(): int
    {
        return $this->row->productSaleElementsId;
    }

    public function getProductId(): int
    {
        return $this->row->productId;
    }

    /**
     * The combination as read for the feed: stock, prices before taxes, attribute values...
     */
    public function getRow(): FeedRow
    {
        return $this->row;
    }

    public function getItem(): FeedItem
    {
        return $this->item;
    }

    /**
     * The absolute address of the product page on the domain of the feed, without any parameter: the
     * base to build another link to the combination.
     */
    public function getProductUrl(): string
    {
        return $this->productUrl;
    }

    public function exclude(?string $reason = null): void
    {
        $this->excluded = true;
        $this->exclusionReason = $reason;
        $this->stopPropagation();
    }

    public function isExcluded(): bool
    {
        return $this->excluded;
    }

    public function getExclusionReason(): ?string
    {
        return $this->exclusionReason;
    }
}
