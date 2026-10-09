<?php

declare(strict_types=1);

namespace GoogleShoppingXml\Tests;

use GoogleShoppingXml\Feed\FeedSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../Feed/FeedSettings.php';

final class FeedSettingsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<int>}>
     */
    public static function lists(): iterable
    {
        yield 'plain list' => ['3,5,8', [3, 5, 8]];
        yield 'spaces' => [' 3 , 5 ', [3, 5]];
        yield 'duplicates' => ['3,5,3', [3, 5]];
        yield 'zero and negative' => ['0,-4,7', [7]];
        yield 'not numbers' => ['a,,7x', [7]];
        yield 'empty' => ['', []];
    }

    /**
     * @param list<int> $expected
     */
    #[DataProvider('lists')]
    public function testParseIds(string $list, array $expected): void
    {
        self::assertSame($expected, FeedSettings::parseIds($list));
    }

    public function testFeatureIdsGatherColorGenderAndMaterialOnce(): void
    {
        $settings = new FeedSettings(colorFeatureIds: [1, 2], genderFeatureIds: [2, 3], materialFeatureIds: [4], imageFilter: 'default');

        self::assertSame([1, 2, 3, 4], $settings->featureIds());
    }
}
