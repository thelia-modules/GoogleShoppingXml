<?php

declare(strict_types=1);

namespace GoogleShoppingXml\Feed;

/**
 * The fields of one <item> of a feed, in the order they are written. A field holds a text, a list of
 * texts (the element is repeated: additional_image_link) or a list of groups of texts (a nested element
 * repeated: shipping, product_detail). Names are written with the "g:" prefix of the Google namespace.
 */
final class FeedItem
{
    /** The names Google expects, written after "g:": lowercase letters, digits, underscores. */
    public const FIELD_NAME = '/^[a-z][a-z0-9_]*$/';

    /**
     * @param array<string, string|list<string>|list<array<string, string>>> $fields
     */
    public function __construct(private array $fields = [])
    {
    }

    public function has(string $name): bool
    {
        return \array_key_exists($name, $this->fields);
    }

    /**
     * @return string|list<string>|list<array<string, string>>|null
     */
    public function get(string $name): string|array|null
    {
        return $this->fields[$name] ?? null;
    }

    /**
     * Sets a field: a field already there keeps its place, a new one is written last.
     *
     * @param string|list<string>|list<array<string, string>> $value
     */
    public function set(string $name, string|array $value): self
    {
        if (1 !== preg_match(self::FIELD_NAME, $name)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not a valid Google Shopping field name.', $name));
        }

        $this->fields[$name] = $value;

        return $this;
    }

    public function remove(string $name): self
    {
        unset($this->fields[$name]);

        return $this;
    }

    /**
     * @return array<string, string|list<string>|list<array<string, string>>>
     */
    public function all(): array
    {
        return $this->fields;
    }
}
