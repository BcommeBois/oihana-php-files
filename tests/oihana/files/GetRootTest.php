<?php

namespace oihana\files ;

use oihana\enums\Char;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\TestCase;

#[CoversFunction('oihana\files\getRoot')]
final class GetRootTest extends TestCase
{
    public function testEmptyPathReturnsEmpty()
    {
        $this->assertSame(Char::EMPTY, getRoot(''));
    }

    public function testUnixRoot()
    {
        $this->assertSame('/', getRoot('/usr/local/bin'));
    }

    public function testUnixRootWithScheme()
    {
        $this->assertSame('file:///', getRoot('file:///usr/local/bin'));
    }

    public function testWindowsDriveLetterWithSlash()
    {
        $this->assertSame('C:/', getRoot('C:/Windows/System32'));
        $this->assertSame('D:/', getRoot('D:/Data'));
    }

    public function testWindowsDriveLetterWithBackslash()
    {
        $this->assertSame('C:/', getRoot('C:\\Windows\\System32'));
        $this->assertSame('E:/', getRoot('E:\\Documents'));
    }

    public function testWindowsDriveOnly()
    {
        $this->assertSame('C:/', getRoot('C:'));
        $this->assertSame('Z:/', getRoot('Z:'));
    }

    public function testWindowsDriveWithScheme()
    {
        $this->assertSame('s3://C:/', getRoot('s3://C:/data'));
    }

    public function testRelativePath()
    {
        $this->assertSame(Char::EMPTY, getRoot('my/relative/path'));
        $this->assertSame(Char::EMPTY, getRoot('folder\\subfolder'));
    }

    public function testPathWithUnknownSchemeAndUnixRoot()
    {
        $this->assertSame('custom:///', getRoot('custom:///var/data'));
    }

    public function testPathWithUnknownSchemeAndRelative()
    {
        $this->assertSame(Char::EMPTY, getRoot('custom://relative/path'));
    }
}