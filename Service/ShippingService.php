<?php

namespace GoogleShoppingXml\Service;

use GoogleShoppingXml\Model\GoogleshoppingxmlFeed;
use Thelia\Tools\MoneyFormat;

/**
 * Turns the delivery rate matrix into the <g:shipping> entries of a feed.
 */
class ShippingService
{
    public function __construct(private ShippingMatrixBuilder $matrixBuilder)
    {
    }

    /**
     * Every bracket, prices already formatted for the feed currency. Built once per generation,
     * then read per product through entriesFor().
     *
     * @return array<string, array<int, array{country: string, service: string, price: string}>>
     */
    public function buildShippingMatrix(GoogleshoppingxmlFeed $feed, MoneyFormat $moneyFormat): array
    {
        $rate = $feed->getCurrency()->getRate();
        $currencyCode = $feed->getCurrency()->getCode();

        $matrix = [];

        foreach ($this->matrixBuilder->build($feed) as $bracket => $entries) {
            foreach ($entries as $entry) {
                $entry['price'] = $moneyFormat->format($entry['price'] * $rate, null, '.', '', $currencyCode);
                $matrix[$bracket][] = $entry;
            }
        }

        return $matrix;
    }

    /**
     * The <g:shipping> entries of one product, picked from the matrix by weight.
     *
     * @param array<string, array<int, array{country: string, service: string, price: string}>> $matrix
     *
     * @return array<int, array{country: string, service: string, price: string}>
     */
    public function entriesFor(array $matrix, ?float $weight, bool $isVirtual): array
    {
        return $matrix[$this->matrixBuilder->bracketFor($weight, $isVirtual)] ?? [];
    }
}
