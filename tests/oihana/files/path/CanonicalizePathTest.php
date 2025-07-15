<?php

namespace oihana\files\path ;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use function oihana\files\getHomeDirectory;

final class CanonicalizePathTest extends TestCase
{
    /** @return iterable<string, array{input:string, expected:string}> */
    public static function unixProvider(): iterable
    {
        yield 'simple dot' =>
        [
            'input'    => '/var/./log',
            'expected' => '/var/log',
        ];

        yield 'dotdot' =>
        [
            'input'    => '/var/www/../log',
            'expected' => '/var/log',
        ];

        yield 'mixed slashes' =>
        [
            'input'    => '/var\\log//app',
            'expected' => '/var/log/app',
        ];
    }

    #[DataProvider('unixProvider')]
    public function testUnixPaths(string $input, string $expected): void
    {
        $this->assertSame($expected, canonicalizePath($input));
    }

    public function testWindowsPath(): void
    {
        $this->assertSame('C:/Logs', canonicalizePath('C:\\Temp\\..\\Logs\\.'));
    }

    public function testHomeExpansion(): void
    {
        getenv('HOME') ?: getenv('HOME', '/home/test');   // ensure HOME set for portability
        $home = getHomeDirectory();
        $this->assertSame($home . '/project', canonicalizePath('~/project'));
    }

    public function testBufferCaching(): void
    {
        $p1 = canonicalizePath('/tmp/example');
        $p2 = canonicalizePath('/tmp/example');     // second call should hit cache
        $this->assertSame($p1, $p2);
    }
}