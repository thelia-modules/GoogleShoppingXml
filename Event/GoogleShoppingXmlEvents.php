<?php

declare(strict_types=1);

namespace GoogleShoppingXml\Event;

final class GoogleShoppingXmlEvents
{
    /**
     * Dispatched for every combination about to be written in a feed (FeedItemEvent): a listener can
     * leave it out, or add, replace or remove a field.
     */
    public const FEED_ITEM = 'googleshoppingxml.feed.item';
}
