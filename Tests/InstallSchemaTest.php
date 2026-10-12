<?php

declare(strict_types=1);

namespace GoogleShoppingXml\Tests;

use GoogleShoppingXml\Api\ExclusionTable;
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../Api/ExclusionTable.php';

/*
 * No database, no kernel:
 *   vendor/bin/phpunit vendor/thelia/modules/GoogleShoppingXml/Tests/InstallSchemaTest.php
 */
final class InstallSchemaTest extends TestCase
{
    private const CONFIG_DIRECTORY = __DIR__.'/../Config';

    protected function tearDown(): void
    {
        ExclusionTable::replaceLookup(null);
    }

    public function testTheliaMainSqlExistsForTheInstallation(): void
    {
        self::assertFileExists(self::CONFIG_DIRECTORY.'/TheliaMain.sql');
        self::assertStringContainsString('TheliaMain.sql=TheliaMain', (string) file_get_contents(self::CONFIG_DIRECTORY.'/sqldb.map'));
    }

    public function testTheliaMainSqlCreatesEveryTableOfTheSchema(): void
    {
        preg_match_all('/<table name="([^"]+)"/', (string) file_get_contents(self::CONFIG_DIRECTORY.'/schema.xml'), $declared);
        self::assertContains(ExclusionTable::NAME, $declared[1]);

        $sql = (string) file_get_contents(self::CONFIG_DIRECTORY.'/TheliaMain.sql');
        foreach ($declared[1] as $table) {
            self::assertStringContainsString(sprintf('CREATE TABLE IF NOT EXISTS `%s`', $table), $sql, $table);
        }
    }

    public function testTheliaMainSqlCanBePlayedAgainWithoutLosingData(): void
    {
        $sql = (string) file_get_contents(self::CONFIG_DIRECTORY.'/TheliaMain.sql');

        self::assertDoesNotMatchRegularExpression('/\bDROP\s+(TABLE|DATABASE)\b/i', $sql);
        self::assertDoesNotMatchRegularExpression('/\bCREATE\s+TABLE\s+(?!IF NOT EXISTS)/i', $sql);
        self::assertDoesNotMatchRegularExpression('/\b(TRUNCATE|DELETE\s+FROM)\b/i', $sql);
    }

    public function testNoUpdateScriptDropsATable(): void
    {
        foreach (glob(self::CONFIG_DIRECTORY.'/update/*.sql') ?: [] as $script) {
            self::assertDoesNotMatchRegularExpression('/\bDROP\s+TABLE\b/i', (string) file_get_contents($script), basename($script));
        }
    }

    public function testTheliaMainSqlIsTheliaSqlWithoutTheDrops(): void
    {
        $withoutDrops = preg_replace('/DROP TABLE IF EXISTS `[^`]+`;\n\n/', '', (string) file_get_contents(self::CONFIG_DIRECTORY.'/thelia.sql'));
        $expected = str_replace('CREATE TABLE `', 'CREATE TABLE IF NOT EXISTS `', (string) $withoutDrops);

        self::assertSame(
            $this->withoutComments($expected),
            $this->withoutComments((string) file_get_contents(self::CONFIG_DIRECTORY.'/TheliaMain.sql')),
        );
    }

    public function testTheUpdateToThisVersionCreatesTheExclusionTableOnlyWhenMissing(): void
    {
        $script = (string) file_get_contents(self::CONFIG_DIRECTORY.'/update/4.1.1.sql');

        self::assertStringContainsString(sprintf('CREATE TABLE IF NOT EXISTS `%s`', ExclusionTable::NAME), $script);
        self::assertDoesNotMatchRegularExpression('/\bDROP\b/i', $script);
    }

    public function testOnlyAPositiveAnswerIsKept(): void
    {
        $calls = 0;
        $answer = false;
        ExclusionTable::replaceLookup(static function () use (&$calls, &$answer): bool {
            ++$calls;

            return $answer;
        });

        self::assertFalse(ExclusionTable::exists());
        self::assertFalse(ExclusionTable::exists());
        self::assertSame(2, $calls);

        $answer = true;
        self::assertTrue(ExclusionTable::exists());
        self::assertTrue(ExclusionTable::exists());
        self::assertSame(3, $calls);
    }

    private function withoutComments(string $sql): string
    {
        return (string) preg_replace('/^(--|#).*\n/m', '', $sql);
    }
}
