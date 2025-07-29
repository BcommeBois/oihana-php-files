<?php

namespace oihana\files ;

use oihana\files\exceptions\DirectoryException;

use oihana\files\exceptions\FileException;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;

use PHPUnit\Framework\TestCase;

class MakeFileTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/makefile_test_' . uniqid();
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDir($this->tmpDir);
    }

    private function deleteDir(string $dir): void
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
                $this->deleteDir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    public function testThrowsExceptionWhenFilePathIsEmpty(): void
    {
        $this->expectException(FileException::class);
        makeFile(null, 'content');
    }

    public function testCreateFileWithContent(): void
    {
        $file = $this->tmpDir . '/file.txt';
        $result = makeFile($file, "Hello World");

        $this->assertSame($file, $result);
        $this->assertFileExists($file);
        $this->assertStringEqualsFile($file, "Hello World");
    }

    public function testAppendToFile(): void
    {
        $file = $this->tmpDir . '/file.txt';
        file_put_contents($file, "Line 1\n");

        makeFile($file, "Line 2\n", ['append' => true]);

        $expected = "Line 1\nLine 2\n";
        $this->assertStringEqualsFile($file, $expected);
    }

    public function testOverwriteFile(): void
    {
        $file = $this->tmpDir . '/file.txt';
        file_put_contents($file, "Old content");

        makeFile($file, "New content", ['overwrite' => true]);

        $this->assertStringEqualsFile($file, "New content");
    }

    public function testDoesNotOverwriteOrAppendByDefault(): void
    {
        $file = $this->tmpDir . '/file.txt';
        file_put_contents($file, "Original");

        $result = makeFile($file, "Should not write");

        // File content unchanged
        $this->assertStringEqualsFile($file, "Original");
        $this->assertSame($file, $result);
    }

    public function testCreatesParentDirectoriesWhenForced(): void
    {
        $nestedDir = $this->tmpDir . '/nested/dir';
        $file = $nestedDir . '/file.txt';

        $result = makeFile($file, "Content", ['force' => true]);

        $this->assertSame($file, $result);
        $this->assertFileExists($file);
        $this->assertStringEqualsFile($file, "Content");
    }

    public function testThrowsExceptionWhenNotWritable(): void
    {
        $file = $this->tmpDir . '/file.txt';
        file_put_contents($file, "content");
        chmod($file, 0444); // read-only

        $this->expectException(FileException::class);

        // Neither overwrite nor append, so should error because file is not writable
        makeFile($file, "new content", ['overwrite' => true]);
    }

    public function testSetPermissions(): void
    {
        $file = $this->tmpDir . '/file.txt';

        makeFile($file, "content", ['permissions' => 0600]);

        $perms = fileperms($file) & 0777;
        $this->assertSame(0600, $perms);
    }

    // Test owner and group only if running on Unix-like system with permissions

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testSetOwnerAndGroup(): void
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $this->markTestSkipped('Owner and group tests skipped on Windows.');
        }

        $file = $this->tmpDir . '/file.txt';

        makeFile($file, "content", [
            'owner' => get_current_user(),
            'group' => posix_getgid(),
        ]);

        // We cannot reliably assert owner/group without posix extension but at least no exception thrown
        $this->assertFileExists($file);
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testSetOwnerAndGroupWithArrayParameter(): void
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $this->markTestSkipped('Owner and group tests skipped on Windows.');
        }

        $file = $this->tmpDir . '/file_from_array.txt';

        makeFile([
            'file'    => $file,
            'content' => "test content",
            'owner'   => get_current_user(),
            'group'   => posix_getgid(),
        ]);

        $this->assertFileExists($file);
    }
}