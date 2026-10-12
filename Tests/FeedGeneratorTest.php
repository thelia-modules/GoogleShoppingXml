<?php

declare(strict_types=1);

namespace GoogleShoppingXml\Tests;

use GoogleShoppingXml\Feed\FeedGenerator;
use GoogleShoppingXml\Feed\FeedItem;
use GoogleShoppingXml\Feed\FeedRow;
use GoogleShoppingXml\Feed\FeedSettings;
use PHPUnit\Framework\TestCase;
use Thelia\Log\Tlog;

require_once __DIR__.'/../Feed/FeedItem.php';
require_once __DIR__.'/../Feed/FeedRow.php';
require_once __DIR__.'/../Feed/FeedSettings.php';
require_once __DIR__.'/../Feed/FeedGenerator.php';
require_once __DIR__.'/Stub/Tlog.php';

final class FeedGeneratorTest extends TestCase
{
    /**
     * @param array<int, list<string>>                                            $featureValues
     * @param list<array{attribute_id: int, value_id: int, title: string}> $attributeValues
     */
    private static function row(array $attributeValues = [], array $featureValues = []): FeedRow
    {
        return new FeedRow(10, 'REF-10', 1, 'P1', true, false, 'Jacket', '', '', 'Brand', 1.0, '', 0.0, null, 1, 10.0, null, false, null, $attributeValues, $featureValues);
    }

    public function testSizeIsEmptyWhenNoAttributeIsSet(): void
    {
        $row = self::row([['attribute_id' => 5, 'value_id' => 50, 'title' => 'M'], ['attribute_id' => 6, 'value_id' => 60, 'title' => 'Black']]);

        self::assertSame('', FeedGenerator::size($row, new FeedSettings(imageFilter: 'default')));
    }

    public function testSizeReadsOnlyTheAttributesSet(): void
    {
        $row = self::row([['attribute_id' => 5, 'value_id' => 50, 'title' => 'M'], ['attribute_id' => 6, 'value_id' => 60, 'title' => 'Black']]);

        self::assertSame('M', FeedGenerator::size($row, new FeedSettings(sizeAttributeIds: [5], imageFilter: 'default')));
        self::assertSame('M - Black', FeedGenerator::size($row, new FeedSettings(sizeAttributeIds: [5, 6], imageFilter: 'default')));
    }

    public function testColorsDropTheHexCodeKeepThreeNamesAndNoDuplicate(): void
    {
        $row = self::row(featureValues: [7 => ['Brown/#8B4513', 'Black', 'Black', 'Red', 'Blue'], 8 => ['Green']]);

        self::assertSame('Brown/Black/Red', FeedGenerator::colors($row, new FeedSettings(colorFeatureIds: [7, 8], imageFilter: 'default')));
    }

    public function testColorsAreEmptyWithoutFeatureSet(): void
    {
        $row = self::row(featureValues: [7 => ['Brown']]);

        self::assertSame('', FeedGenerator::colors($row, new FeedSettings(imageFilter: 'default')));
    }

    public function testXmlWritesTextRepeatedAndNestedFieldsAndSkipsEmptyOnes(): void
    {
        $item = new FeedItem([
            'id' => '10',
            'title' => 'Jacket <b> & "co"',
            'size' => '',
            'additional_image_link' => ['https://a/1.jpg', 'https://a/2.jpg'],
            'shipping' => [['country' => 'FR', 'price' => '5.00 EUR']],
        ]);

        self::assertSame(
            "<item>\n"
            ."<g:id>10</g:id>\n"
            ."<g:title>Jacket &lt;b&gt; &amp; \"co\"</g:title>\n"
            ."<g:additional_image_link>https://a/1.jpg</g:additional_image_link>\n"
            ."<g:additional_image_link>https://a/2.jpg</g:additional_image_link>\n"
            ."<g:shipping>\n<g:country>FR</g:country>\n<g:price>5.00 EUR</g:price>\n</g:shipping>\n"
            ."</item>\n",
            FeedGenerator::xml($item),
        );
    }

    public function testXmlRemovesTheCharactersXmlForbids(): void
    {
        self::assertSame("<item>\n<g:title>ab</g:title>\n</item>\n", FeedGenerator::xml(new FeedItem(['title' => "a\x01\x0Bb"])));
    }

    public function testXmlKeepsTheTextWhenItIsNotValidUtf8AndReportsIt(): void
    {
        Tlog::$warnings = [];

        $xml = FeedGenerator::xml(new FeedItem(['title' => "caf\xE9 noir"]));

        self::assertStringContainsString('<g:title>caf', $xml);
        self::assertStringContainsString(' noir</g:title>', $xml);
        self::assertCount(1, Tlog::$warnings);
    }
}
