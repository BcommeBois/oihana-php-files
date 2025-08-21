<?php

namespace tests\oihana\files\images;

use oihana\files\exceptions\DirectoryException;
use oihana\files\exceptions\FileException;
use PHPUnit\Framework\TestCase;
use function oihana\files\deleteDirectory;
use function oihana\files\makeDirectory;

class GetImageMimeTypeTest extends TestCase
{
    private string $tempDir;

    /**
     * @throws DirectoryException
     */
    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/oihana-php-files/images/image_mime_test';
        makeDirectory($this->tempDir);
    }

    /**
     * @throws DirectoryException
     */
    protected function tearDown(): void
    {
        deleteDirectory($this->tempDir);
    }

    private function createTempFile(string $name, string $content): string
    {
        $path = $this->tempDir . '/' . $name;
        file_put_contents($path, $content);
        return $path;
    }

    /**
     * @throws FileException
     */
    public function testDetectsPngMime(): void
    {
        $file = $this->createTempFile('test.png', hex2bin('89504E470D0A1A0A0000000D49484452'));
        $mime = getImageMimeType($file);
        $this->assertSame('image/png', $mime);
    }

    /**
     * @throws FileException
     */
    public function testDetectsJpegMime(): void
    {
        $file = $this->createTempFile('test.jpg', hex2bin('FFD8FFE000104A46494600010100000100010000'));
        $mime = getImageMimeType($file, 'jpg');
        $this->assertSame('image/jpeg', $mime);
    }

    /**
     * @throws FileException
     */
    public function testFallbackWhenFormatMismatch(): void
    {
        $file = $this->createTempFile('test.png', hex2bin('89504E470D0A1A0A0000000D49484452'));
        $mime = getImageMimeType($file, 'jpg'); // format incorrect
        $this->assertSame('image/png', $mime);
    }

    /**
     * @throws FileException
     */
    public function testUsesAllowedFormats(): void
    {
        $file = $this->createTempFile('test.html', '<!DOCTYPE html><html></html>');
        $allowed = [ 'html' => 'text/html' ];
        $mime = getImageMimeType($file, 'html', $allowed);
        $this->assertSame('text/html', $mime);
    }

    /**
     * @throws FileException
     */
    public function testReturnsRealMimeWhenFormatNotProvided(): void
    {
        $file = $this->createTempFile('test.gif', hex2bin('47494638396101000100'));
        $mime = getImageMimeType($file);
        $this->assertSame('image/gif', $mime);
    }

    /**
     * @throws FileException
     */
    public function testThrowsOnMissingFile(): void
    {
        $this->expectException(FileException::class);
        getImageMimeType($this->tempDir . '/missing.png');
    }
}