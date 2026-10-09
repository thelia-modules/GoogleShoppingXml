<?php

declare(strict_types=1);

namespace GoogleShoppingXml\Exception;

/**
 * A feed that cannot be generated: the file in place, if any, is left untouched.
 */
final class FeedGenerationException extends \RuntimeException
{
    public static function unknownFeed(string $feed): self
    {
        return new self(\sprintf('No Google Shopping feed "%s".', $feed));
    }

    public static function missingStoreSetting(string $setting, string $locale): self
    {
        return new self(\sprintf('The store setting "%s" is empty for %s: set it in Configuration > Store.', $setting, $locale));
    }

    public static function unknownImageFilter(string $filter): self
    {
        return new self(\sprintf('The image filter set "%s" does not exist.', $filter));
    }

    public static function noShopUrl(string $locale): self
    {
        return new self(\sprintf('No address for the shop in %s: set the shop URL, or the URL of the language when each language has its domain.', $locale));
    }

    public static function notWritable(string $path): self
    {
        return new self(\sprintf('The feed file %s cannot be written.', $path));
    }

    public static function emptyFeed(string $feed): self
    {
        return new self(\sprintf('No product in the feed "%s": the previous file is kept.', $feed));
    }
}
