<?php

namespace oihana\files\path ;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExtractCanonicalPartsTest extends TestCase
{
    /** @return iterable<string, array{root:string, input:string, expected:array}] */
    public static function provider(): iterable
    {
        yield 'unix simple collapse' =>
        [
            'root'     => '/var/www',
            'input'    => 'project/../cache/./logs',
            'expected' => ['cache', 'logs'],
        ];

        yield 'keep leading dotdot when no root' =>
        [
            'root'     => '',
            'input'    => '../../folder',
            'expected' => ['..', '..', 'folder'],
        ];

        yield 'multiple dotdot inside' =>
        [
            'root'     => '/home',
            'input'    => 'a/b/../../c',
            'expected' => ['c'],
        ];

        yield 'consecutive dot.dots preserved after root' =>
        [
            'root'     => '/root',
            'input'    => '..',
            'expected' => [],          // ".." after absolute root => removed
        ];

        yield 'empty input' =>
        [
            'root'     => '' ,
            'input'    => '' ,
            'expected' => [] ,
        ];
    }

    #[DataProvider('provider')]
    public function testExtractCanonical(string $root, string $input, array $expected): void
    {
        $this->assertSame($expected, extractCanonicalParts($root, $input));
    }
}