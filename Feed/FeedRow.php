<?php

declare(strict_types=1);

namespace GoogleShoppingXml\Feed;

/**
 * One combination (product sale elements) as read from the database for a feed, before any rule.
 */
final readonly class FeedRow
{
    /**
     * @param list<array{attribute_id: int, value_id: int, title: string}> $attributeValues the translated values of the combination
     * @param array<int, list<string>>                                     $featureValues   translated values of the product, by feature id
     * @param list<string>                                                 $imageFiles      the visible images, first one first
     */
    public function __construct(
        public int $productSaleElementsId,
        public string $reference,
        public int $productId,
        public string $productReference,
        public bool $isVisible,
        public bool $isExcluded,
        public string $title,
        public string $subtitle,
        public string $description,
        public string $brand,
        public float $quantity,
        public string $eanCode,
        public float $weight,
        public ?int $defaultCategoryId,
        public int $taxRuleId,
        public ?float $price,
        public ?float $promoPrice,
        public bool $isPromo,
        public ?string $rewrittenUrl,
        public array $attributeValues = [],
        public array $featureValues = [],
        public array $imageFiles = [],
    ) {
    }

    public function withDetails(array $attributeValues, array $featureValues, array $imageFiles): self
    {
        return new self(
            $this->productSaleElementsId,
            $this->reference,
            $this->productId,
            $this->productReference,
            $this->isVisible,
            $this->isExcluded,
            $this->title,
            $this->subtitle,
            $this->description,
            $this->brand,
            $this->quantity,
            $this->eanCode,
            $this->weight,
            $this->defaultCategoryId,
            $this->taxRuleId,
            $this->price,
            $this->promoPrice,
            $this->isPromo,
            $this->rewrittenUrl,
            $attributeValues,
            $featureValues,
            $imageFiles,
        );
    }
}
