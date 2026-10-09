<?php

declare(strict_types=1);

namespace GoogleShoppingXml\Feed;

use Thelia\Model\Currency;
use Thelia\Model\Lang;

/**
 * Writes an amount as the language of the feed writes numbers (decimals, separators) followed by the
 * currency as its format says ("%n %s": "1 099,94 €"). Independent of any request: a feed reads the
 * same in a scheduled task and from the back office.
 */
final readonly class PriceFormatter
{
    private int $decimals;

    private string $decimalSeparator;

    private string $thousandsSeparator;

    public function __construct(Lang $lang, private Currency $currency)
    {
        $decimals = $lang->getDecimals();
        $this->decimals = null === $decimals || '' === (string) $decimals ? 2 : (int) $decimals;
        $this->decimalSeparator = (string) ($lang->getDecimalSeparator() ?? '.');
        $this->thousandsSeparator = (string) ($lang->getThousandsSeparator() ?? '');
    }

    public function format(float $amount): string
    {
        $number = number_format($amount, $this->decimals, $this->decimalSeparator, $this->thousandsSeparator);
        $format = (string) $this->currency->getFormat();

        if (!str_contains($format, '%n')) {
            return $number.' '.$this->currency->getCode();
        }

        return str_replace(['%n', '%s', '%c'], [$number, (string) $this->currency->getSymbol(), (string) $this->currency->getCode()], $format);
    }
}
