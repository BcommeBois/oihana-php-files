<?php

namespace tests\oihana\files\path;

use oihana\files\enums\CanonicalizeBuffer;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use function oihana\files\getHomeDirectory;
use function oihana\files\path\canonicalizePath;

#[CoversFunction('oihana\files\path\canonicalizePath')]
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

    public function testEmptyPathReturnsEmpty(): void
    {
        $this->assertSame('', canonicalizePath(''));
    }

    public function testBufferCleanupTriggersAboveThreshold(): void
    {
        // Reset the shared static buffer so the count is deterministic.
        CanonicalizeBuffer::$buffer     = [];
        CanonicalizeBuffer::$bufferSize = 0;

        // One distinct path past the threshold triggers the soft-LRU clean-up,
        // which trims the buffer back down to CLEANUP_SIZE.
        $count = CanonicalizeBuffer::CLEANUP_THRESHOLD + 1;
        for ($i = 0; $i < $count; $i++)
        {
            canonicalizePath('/tmp/cleanup/path' . $i);
        }

        $this->assertSame(CanonicalizeBuffer::CLEANUP_SIZE, CanonicalizeBuffer::$bufferSize);
        $this->assertLessThanOrEqual(CanonicalizeBuffer::CLEANUP_SIZE, count(CanonicalizeBuffer::$buffer));
    }
}