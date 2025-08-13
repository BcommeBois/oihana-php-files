<?php

namespace oihana\files\enums ;

use oihana\reflect\exceptions\ConstantException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FindMode::class)]
class FindModeTest extends TestCase
{
    public function testConstantsExist(): void
    {
        $expected = [
            'FILTER',
            'FOLLOW_LINKS',
            'INCLUDE_DOTS',
            'MODE',
            'ORDER',
            'PATTERN',
            'RECURSIVE',
            'SORT',
        ];
        foreach ($expected as $const) {
            self::assertTrue(defined(FindFileOption::class . "::{$const}"), "Constant {$const} should be defined");
        }
    }

    public function testEnumsReturnsAllValues(): void
    {
        $enumValues = FindFileOption::enums();
        $expected   = [
            FindFileOption::FILTER,
            FindFileOption::FOLLOW_LINKS,
            FindFileOption::INCLUDE_DOTS,
            FindFileOption::MODE,
            FindFileOption::ORDER,
            FindFileOption::PATTERN,
            FindFileOption::RECURSIVE,
            FindFileOption::SORT,
        ];
        sort($expected, SORT_STRING);
        self::assertSame($expected, $enumValues);
    }

    public function testIncludesAndValidate(): void
    {
        $this->assertTrue(FindFileOption::includes('mode'));
        $this->assertFalse(FindFileOption::includes('nonexistent'));
        $this->expectException(ConstantException::class);
        FindFileOption::validate('invalid');
    }

    public function testGetReturnsValueOrDefault(): void
    {
        $this->assertSame('mode', FindFileOption::get('mode', 'fallback'));
        $this->assertSame('fallback', FindFileOption::get('invalid', 'fallback'));
    }

    public function testGetConstantByValue(): void
    {
        $name = FindFileOption::getConstant('mode');
        $this->assertSame('MODE', $name);
        $this->assertNull(FindFileOption::getConstant('does_not_exist'));
    }

    public function testResetCachesDoesNotBreakEnum(): void
    {
        FindFileOption::resetCaches();
        $this->assertTrue(FindFileOption::includes('filter'));
    }
}