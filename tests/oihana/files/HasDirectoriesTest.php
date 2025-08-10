<?php

namespace oihana\files ;

use oihana\files\exceptions\DirectoryException;
use PHPUnit\Framework\TestCase;

final class HasDirectoriesTest extends TestCase
{
    private string $tempDir;

    /**
     * @throws DirectoryException
     */
    protected function setUp(): void
    {
        // Crée un dossier temporaire pour chaque test
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'oihana_test_' . uniqid();
        makeDirectory($this->tempDir);
    }

    /**
     * @throws DirectoryException
     */
    protected function tearDown(): void
    {
       deleteDirectory($this->tempDir);
    }

    public function testThrowsExceptionOnNull(): void
    {
        $this->expectException(DirectoryException::class);
        hasDirectories(null);
    }

    /**
     * @throws DirectoryException
     */
    public function testReturnsFalseForEmptyDirectory(): void
    {
        $this->assertFalse(hasDirectories($this->tempDir));
    }

    /**
     * @throws DirectoryException
     */
    public function testReturnsFalseWhenOnlyFilesPresent(): void
    {
        file_put_contents($this->tempDir . DIRECTORY_SEPARATOR . 'file1.txt', 'test');
        $this->assertFalse(hasDirectories($this->tempDir));
    }

    /**
     * @throws DirectoryException
     */
    public function testReturnsTrueWhenSubdirectoryPresent(): void
    {
        mkdir($this->tempDir . DIRECTORY_SEPARATOR . 'subdir');
        $this->assertTrue(hasDirectories($this->tempDir));
    }

    /**
     * @throws DirectoryException
     */
    public function testReturnsTrueInStrictModeWhenOnlyDirectories(): void
    {
        mkdir($this->tempDir . DIRECTORY_SEPARATOR . 'subdir1');
        mkdir($this->tempDir . DIRECTORY_SEPARATOR . 'subdir2');
        $this->assertTrue(hasDirectories($this->tempDir, true));
    }

    /**
     * @throws DirectoryException
     */
    public function testReturnsFalseInStrictModeWhenFilesAlsoPresent(): void
    {
        mkdir($this->tempDir . DIRECTORY_SEPARATOR . 'subdir1');
        file_put_contents($this->tempDir . DIRECTORY_SEPARATOR . 'file1.txt', 'test');
        $this->assertFalse(hasDirectories($this->tempDir, true));
    }
}