<?php

declare(strict_types=1);

namespace GoogleShoppingXml\Tests;

use GoogleShoppingXml\Tools\GtinChecker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../Tools/GtinChecker.php';

/*
 * No database, no kernel:
 *   vendor/bin/phpunit vendor/thelia/modules/GoogleShoppingXml/Tests/GtinCheckerTest.php
 */
final class GtinCheckerTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function validCodes(): iterable
    {
        yield 'EAN-13' => ['4006381333931'];
        yield 'EAN-13 whose check digit differs with the weights counted from the left' => ['3700000000037'];
        yield 'EAN-8' => ['96385074'];
        yield 'UPC-A (12 digits)' => ['036000291452'];
        yield 'GTIN-14' => ['00196178033473'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidCodes(): iterable
    {
        yield 'wrong check digit' => ['4006381333932'];
        yield 'too short' => ['1234'];
        yield 'not a number' => ['40063813339A1'];
        yield 'leading space' => [' 4006381333931'];
        yield 'exponent notation' => ['1e12'];
        yield 'exponent notation of a valid length' => ['1e1234567890'];
        yield 'empty' => [''];
    }

    #[DataProvider('validCodes')]
    public function testAcceptsAValidCode(string $code): void
    {
        self::assertTrue((new GtinChecker())->isValidGtin($code));
    }

    #[DataProvider('invalidCodes')]
    public function testRefusesAnInvalidCode(string $code): void
    {
        self::assertFalse((new GtinChecker())->isValidGtin($code));
    }
}
