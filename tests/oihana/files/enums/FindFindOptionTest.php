<?php

namespace oihana\files\enums ;

use oihana\reflections\exceptions\ConstantException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FindFileOption::class)]
class FindFindOptionTest extends TestCase
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

        foreach ($expected as $const)
        {
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

        // validate() should throw for invalid constant value
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
        // Unique mapping: should return scalar string
        $name = FindFileOption::getConstant('mode');
        $this->assertSame('MODE', $name);

        // Non‑existing value should return null
        $this->assertNull(FindFileOption::getConstant('does_not_exist'));
    }

    public function testResetCachesDoesNotBreakEnum(): void
    {
        FindFileOption::resetCaches();
        $this->assertTrue(FindFileOption::includes('filter'));
    }
}