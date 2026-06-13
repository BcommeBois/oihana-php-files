<?php

namespace tests\oihana\files;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function oihana\files\path\canonicalizePath;
use function oihana\files\getHomeDirectory;

#[CoversFunction('oihana\files\getHomeDirectory')]
final class GetHomeDirectoryTest extends TestCase
{
    /** @var array<string,string|false> */
    private array $saved = [];

    protected function setUp(): void
    {
        foreach (['HOME', 'HOMEDRIVE', 'HOMEPATH'] as $k)
        {
            $this->saved[$k] = getenv($k);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->saved as $k => $v)
        {
            $v === false ? putenv($k) : putenv("$k=$v");
        }
    }

    public function testUsesHomeWhenSet(): void
    {
        putenv('HOME=/home/alice');
        $this->assertSame(canonicalizePath('/home/alice'), getHomeDirectory());
    }

    public function testFallsBackToWindowsHomeDriveAndPath(): void
    {
        putenv('HOME');                       // unset -> first branch skipped
        putenv('HOMEDRIVE=C:');
        putenv('HOMEPATH=\\Users\\Alice');

        $this->assertSame(canonicalizePath('C:\\Users\\Alice'), getHomeDirectory());
    }

    public function testThrowsWhenNoHomeInformation(): void
    {
        putenv('HOME');
        putenv('HOMEDRIVE');
        putenv('HOMEPATH');

        $this->expectException(RuntimeException::class);
        getHomeDirectory();
    }
}
