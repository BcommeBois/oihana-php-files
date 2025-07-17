<?php

namespace oihana\files ;

use oihana\files\exceptions\DirectoryException;
use PHPUnit\Framework\TestCase;

class CopyFilteredFilesTest extends TestCase
{
    private string $sourceDir;
    private string $destDir;

    protected function setUp(): void
    {
        $this->sourceDir = sys_get_temp_dir() . '/source_' . uniqid();
        $this->destDir   = sys_get_temp_dir() . '/dest_' . uniqid();

        mkdir($this->sourceDir, 0777, true);
        mkdir($this->destDir, 0777, true);

        file_put_contents($this->sourceDir . '/file1.txt', 'file 1');
        file_put_contents($this->sourceDir . '/file2.log', 'file 2');

        mkdir($this->sourceDir . '/subdir');
        file_put_contents($this->sourceDir . '/subdir/file3.txt', 'file 3');
    }

    /**
     * @return void
     * @throws DirectoryException
     */
    protected function tearDown(): void
    {
        deleteDirectory($this->sourceDir);
        deleteDirectory($this->destDir);
    }

    /**
     * @throws DirectoryException
     */
    public function testCopyAllFiles(): void
    {
        $result = copyFilteredFiles($this->sourceDir, $this->destDir, [], null);

        $this->assertTrue($result);
        $this->assertFileExists($this->destDir . '/file1.txt');
        $this->assertFileExists($this->destDir . '/file2.log');
        $this->assertFileExists($this->destDir . '/subdir/file3.txt');
    }

    /**
     * @throws DirectoryException
     */
    public function testCopyWithExcludePattern(): void
    {
        $result = copyFilteredFiles($this->sourceDir, $this->destDir, ['~\.log$~'], null);

        $this->assertTrue($result);
        $this->assertFileExists($this->destDir . '/file1.txt');
        $this->assertFileDoesNotExist($this->destDir . '/file2.log');
        $this->assertFileExists($this->destDir . '/subdir/file3.txt');
    }

    /**
     * @throws DirectoryException
     */
    public function testCopyWithFilterCallback(): void
    {
        $callback = fn(string $path) => str_ends_with($path, '.txt');

        $result = copyFilteredFiles($this->sourceDir, $this->destDir, [], $callback);

        $this->assertTrue($result);
        $this->assertFileExists($this->destDir . '/file1.txt');
        $this->assertFileDoesNotExist($this->destDir . '/file2.log');
        $this->assertFileExists($this->destDir . '/subdir/file3.txt');
    }

    /**
     * @throws DirectoryException
     */
    public function testCopyWithAllFilesExcluded(): void
    {
        $result = copyFilteredFiles($this->sourceDir, $this->destDir, ['~.*~'], null);

        $this->assertFalse($result);
        $this->assertDirectoryExists($this->destDir);
        $this->assertFileDoesNotExist($this->destDir . '/file1.txt');
    }
}