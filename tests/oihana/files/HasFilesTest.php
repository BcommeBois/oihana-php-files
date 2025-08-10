<?php

namespace oihana\files ;

use oihana\files\exceptions\DirectoryException;
use PHPUnit\Framework\TestCase;

final class HasFilesTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'oihana_test_' . uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    public function testThrowsExceptionOnNull(): void
    {
        $this->expectException(DirectoryException::class);
        \oihana\files\hasFiles(null);
    }

    public function testReturnsFalseForEmptyDirectory(): void
    {
        $this->assertFalse(\oihana\files\hasFiles($this->tempDir));
    }

    public function testReturnsFalseWhenOnlyDirectoriesPresent(): void
    {
        mkdir($this->tempDir . DIRECTORY_SEPARATOR . 'subdir');
        $this->assertFalse(\oihana\files\hasFiles($this->tempDir));
    }

    public function testReturnsTrueWhenFilePresent(): void
    {
        file_put_contents($this->tempDir . DIRECTORY_SEPARATOR . 'file1.txt', 'test');
        $this->assertTrue(\oihana\files\hasFiles($this->tempDir));
    }

    public function testReturnsTrueInStrictModeWhenOnlyFiles(): void
    {
        file_put_contents($this->tempDir . DIRECTORY_SEPARATOR . 'file1.txt', 'test');
        file_put_contents($this->tempDir . DIRECTORY_SEPARATOR . 'file2.log', 'test');
        $this->assertTrue(\oihana\files\hasFiles($this->tempDir, true));
    }

    public function testReturnsFalseInStrictModeWhenDirectoriesAlsoPresent(): void
    {
        mkdir($this->tempDir . DIRECTORY_SEPARATOR . 'subdir1');
        file_put_contents($this->tempDir . DIRECTORY_SEPARATOR . 'file1.txt', 'test');
        $this->assertFalse(\oihana\files\hasFiles($this->tempDir, true));
    }
}