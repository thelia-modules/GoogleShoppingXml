<?php

declare(strict_types=1);

namespace GoogleShoppingXml\Tests;

use GoogleShoppingXml\Feed\FeedItem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../Feed/FeedItem.php';

/*
 * No database, no kernel:
 *   vendor/bin/phpunit vendor/thelia/modules/GoogleShoppingXml/Tests
 */
final class FeedItemTest extends TestCase
{
    public function testAFieldAlreadyThereKeepsItsPlaceAndANewOneIsWrittenLast(): void
    {
        $item = new FeedItem(['id' => '1', 'title' => 'A']);
        $item->set('title', 'B')->set('custom_label_0', 'summer');

        self::assertSame(['id' => '1', 'title' => 'B', 'custom_label_0' => 'summer'], $item->all());
    }

    public function testRemoveDropsTheField(): void
    {
        $item = new FeedItem(['id' => '1', 'title' => 'A']);
        $item->remove('title');

        self::assertFalse($item->has('title'));
        self::assertNull($item->get('title'));
        self::assertSame('1', $item->get('id'));
    }

    public function testHasSeesAFieldWhoseValueIsEmpty(): void
    {
        self::assertTrue((new FeedItem(['size' => '']))->has('size'));
    }

    #[DataProvider('invalidNames')]
    public function testANameThatIsNotAnXmlElementNameIsRefused(string $name): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new FeedItem())->set($name, 'x');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidNames(): iterable
    {
        yield 'upper case' => ['Title'];
        yield 'starts with a digit' => ['1st'];
        yield 'space' => ['custom label'];
        yield 'colon' => ['g:title'];
        yield 'empty' => [''];
    }
}
