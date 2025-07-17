<?php

namespace oihana\files\archive\tar;

use oihana\files\exceptions\DirectoryException;
use PHPUnit\Framework\TestCase;
use function oihana\files\deleteDirectory;

class HasTarMimeTypeTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/tar_test_' . uniqid();
        mkdir($this->tmpDir);
    }

    /**
     * @throws DirectoryException
     */
    protected function tearDown(): void
    {
        deleteDirectory($this->tmpDir);
    }

    private function createTempFile(string $filename, string $content): string
    {
        $filePath = $this->tmpDir . '/' . $filename;
        file_put_contents($filePath, $content);
        return $filePath;
    }

    public function testRecognizedTarMimeType()
    {
        // We simulate a tar-like file by using known binary content (e.g., a gzipped file)
        $gzFile = $this->createTempFile('test.gz', gzencode('test content'));
        $result = hasTarMimeType($gzFile);
        $this->assertTrue($result, 'Should detect a gzip file as tar-related');
    }

    public function testUnrecognizedMimeType()
    {
        $txtFile = $this->createTempFile('test.txt', 'plain text file');
        $result = hasTarMimeType($txtFile);
        $this->assertFalse($result, 'Should not detect a plain text file as tar-related');
    }

    public function testNonExistentFile()
    {
        $result = hasTarMimeType($this->tmpDir . '/nonexistent.file');
        $this->assertFalse($result, 'Should return false if the file does not exist');
    }

    public function testCustomMimeTypes()
    {
        $customMimeTypes = ['text/plain'];
        $txtFile = $this->createTempFile('custom.txt', 'this is plain text');
        $result = hasTarMimeType($txtFile, $customMimeTypes);
        $this->assertTrue($result, 'Should match using a custom mime type list');
    }
}