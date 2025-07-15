<?php

namespace oihana\files\path ;

use PHPUnit\Framework\TestCase;

final class IsLocalPathTest extends TestCase
{
    public function testEmptyPathReturnsFalse(): void
    {
        $this->assertFalse(isLocalPath(''), 'Empty path should return false');
    }

    public function testLocalUnixPathReturnsTrue(): void
    {
        $this->assertTrue(isLocalPath('/var/www/html/index.php'));
    }

    public function testLocalWindowsPathReturnsTrue(): void
    {
        $this->assertTrue(isLocalPath('C:\\Users\\Admin\\file.txt'));
    }

    public function testHttpUrlReturnsFalse(): void
    {
        $this->assertFalse(isLocalPath('http://example.com/file.txt'));
    }

    public function testFtpUrlReturnsFalse(): void
    {
        $this->assertFalse(isLocalPath('ftp://host/file.txt'));
    }

    public function testS3PathReturnsFalse(): void
    {
        $this->assertFalse(isLocalPath('s3://bucket-name/path/to/file'));
    }

    public function testStreamWrapperReturnsFalse(): void
    {
        $this->assertFalse(isLocalPath('php://temp'));
    }

    public function testDataStreamReturnsFalse(): void
    {
        $this->assertFalse(isLocalPath('data://text/plain;base64,SGVsbG8='));
    }
}