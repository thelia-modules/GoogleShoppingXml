<?php

declare(strict_types=1);

namespace GoogleShoppingXml\Tests;

use GoogleShoppingXml\Api\ExclusionTable;
use GoogleShoppingXml\Api\Resource\GoogleShoppingXmlProductExcluded;
use GoogleShoppingXml\Model\GoogleshoppingxmlProductExcludedQuery;
use PHPUnit\Framework\TestCase;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Thelia\Api\Resource\ProductSaleElements as ProductSaleElementsResource;
use Thelia\Model\ProductSaleElements;

require_once __DIR__.'/Stub/AddonDependencies.php';
require_once __DIR__.'/../Api/ExclusionTable.php';
require_once __DIR__.'/../Api/Resource/GoogleShoppingXmlProductExcluded.php';

/*
 * No database, no kernel:
 *   vendor/bin/phpunit vendor/thelia/modules/GoogleShoppingXml/Tests/ApiExtensionTest.php
 */
final class ApiExtensionTest extends TestCase
{
    protected function setUp(): void
    {
        GoogleshoppingxmlProductExcludedQuery::$calls = [];
    }

    protected function tearDown(): void
    {
        ExclusionTable::replaceLookup(null);
    }

    public function testCombinationsAreReadWithoutTheJoinWhenTheTableIsMissing(): void
    {
        ExclusionTable::replaceLookup(static fn (): bool => false);
        $query = new ModelCriteria();

        GoogleShoppingXmlProductExcluded::extendQuery($query);

        self::assertFalse($query->joined);
    }

    public function testCombinationsAreReadWithTheJoinWhenTheTableExists(): void
    {
        ExclusionTable::replaceLookup(static fn (): bool => true);
        $query = new ModelCriteria();

        GoogleShoppingXmlProductExcluded::extendQuery($query);

        self::assertTrue($query->joined);
    }

    public function testBuildFromModelPassesItsTurnWhenTheTableIsMissing(): void
    {
        ExclusionTable::replaceLookup(static fn (): bool => false);
        $addon = new GoogleShoppingXmlProductExcluded();

        $built = $addon->buildFromModel(new ProductSaleElements(12), new ProductSaleElementsResource());

        self::assertSame($addon, $built);
        self::assertFalse($addon->isExcluded());
        self::assertSame([], GoogleshoppingxmlProductExcludedQuery::$calls);
    }

    public function testDoDeletePassesItsTurnWhenTheTableIsMissing(): void
    {
        ExclusionTable::replaceLookup(static fn (): bool => false);

        (new GoogleShoppingXmlProductExcluded())->doDelete(new ProductSaleElements(12), new ProductSaleElementsResource());

        self::assertSame([], GoogleshoppingxmlProductExcludedQuery::$calls);
    }

    public function testDoDeleteFiltersByThePseIdColumn(): void
    {
        ExclusionTable::replaceLookup(static fn (): bool => true);

        (new GoogleShoppingXmlProductExcluded())->doDelete(new ProductSaleElements(12), new ProductSaleElementsResource());

        self::assertSame(['filterByPseId(12)', 'delete'], GoogleshoppingxmlProductExcludedQuery::$calls);
    }

    public function testDoSaveRefusesAnExclusionWhenTheTableIsMissing(): void
    {
        ExclusionTable::replaceLookup(static fn (): bool => false);
        $addon = (new GoogleShoppingXmlProductExcluded())->setIsExcluded(true);

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('module:schema:apply GoogleShoppingXml');

        $addon->doSave(new ProductSaleElements(12), new ProductSaleElementsResource());
    }

    public function testDoSaveAcceptsTheDefaultValueWhenTheTableIsMissing(): void
    {
        ExclusionTable::replaceLookup(static fn (): bool => false);

        (new GoogleShoppingXmlProductExcluded())->doSave(new ProductSaleElements(12), new ProductSaleElementsResource());

        self::assertSame([], GoogleshoppingxmlProductExcludedQuery::$calls);
    }
}
