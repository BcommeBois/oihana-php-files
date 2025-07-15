<?php

namespace oihana\files\path ;

use PHPUnit\Framework\TestCase;

final class SplitPathTest extends TestCase
{
    public function testEmptyPathReturnsEmptyRootAndRemainder(): void
    {
        [$root, $remainder] = splitPath('');
        $this->assertSame('', $root);
        $this->assertSame('', $remainder);
    }

    public function testUnixAbsolutePath(): void
    {
        [$root, $remainder] = splitPath('/var/www/html');
        $this->assertSame('/', $root);
        $this->assertSame('var/www/html', $remainder);
    }

    public function testUnixRootOnly(): void
    {
        [$root, $remainder] = splitPath('/');
        $this->assertSame('/', $root);
        $this->assertSame('', $remainder);
    }

    public function testWindowsDriveWithPath(): void
    {
        [$root, $remainder] = splitPath('C:/Program Files/App');
        $this->assertSame('C:/', $root);
        $this->assertSame('Program Files/App', $remainder);
    }

    public function testWindowsDriveOnly(): void
    {
        [$root, $remainder] = splitPath('C:');
        $this->assertSame('C:/', $root);
        $this->assertSame('', $remainder);
    }

    public function testUrlScheme(): void
    {
        [$root, $remainder] = splitPath('file:///usr/local/bin');
        $this->assertSame('file:///', $root);
        $this->assertSame('usr/local/bin', $remainder);
    }

    public function testUrlSchemeWithWindowsDrive(): void
    {
        [$root, $remainder] = splitPath('file://C:/Windows/System32');
        $this->assertSame('file://C:/', $root);
        $this->assertSame('Windows/System32', $remainder);
    }

    public function testRelativePath(): void
    {
        [$root, $remainder] = splitPath('some/relative/path');
        $this->assertSame('', $root);
        $this->assertSame('some/relative/path', $remainder);
    }

    public function testWindowsDriveLetterWithoutSlash(): void
    {
        [$root, $remainder] = splitPath('D:folder/file.txt');
        $this->assertSame('', $root);
        $this->assertSame('D:folder/file.txt', $remainder);
    }
}