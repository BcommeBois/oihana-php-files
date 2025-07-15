<?php

namespace oihana\files\path ;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NormalizePathTest extends TestCase
{
    /** @return iterable<string, array{input:string, expected:string}> */
    public static function pathsProvider(): iterable
    {
        yield 'pure windows' =>
        [
            'input'    => 'C:\\Users\\john\\Docs',
            'expected' => 'C:/Users/john/Docs',
        ];

        yield 'mixed separators' =>
        [
            'input'    => 'C:/Temp\\mix\\of/slashes',
            'expected' => 'C:/Temp/mix/of/slashes',
        ];

        yield 'already unix' =>
        [
            'input'    => '/var/www/html',
            'expected' => '/var/www/html',
        ];

        yield 'trailing backslash' =>
        [
            'input'    => 'D:\\data\\',
            'expected' => 'D:/data/',
        ];

        yield 'network path' =>
        [
            'input'    => '\\\\server\\share\\folder',
            'expected' => '//server/share/folder',
        ];
    }

    #[DataProvider('pathsProvider')]
    public function testNormalizePath(string $input, string $expected): void
    {
        $this->assertSame($expected, normalizePath($input));
    }
}