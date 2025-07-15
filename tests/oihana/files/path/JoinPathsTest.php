<?php

namespace oihana\files\path ;

use PHPUnit\Framework\TestCase;

final class JoinPathsTest extends TestCase
{
    public function testUnixJoin(): void
    {
        $this->assertSame('/var/log/app.log', joinPaths('/var', 'log', 'app.log'));
    }

    public function testWindowsJoin(): void
    {
        $this->assertSame('C:/Logs', joinPaths('C:\\', 'Temp', '..', 'Logs'));
    }

    public function testSchemeJoin(): void
    {
        $this->assertSame(
            'phar://archive.phar/sub/file.php',
            joinPaths('phar://archive.phar', '/sub', '/file.php')
        );
    }

    public function testIgnoresEmptyFragments(): void
    {
        $this->assertSame('relative/path', joinPaths('', 'relative', '', 'path'));
    }

    public function testAllEmptyReturnsEmpty(): void
    {
        $this->assertSame('', joinPaths('', '', ''));
    }
}