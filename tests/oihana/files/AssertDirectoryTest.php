<?php

namespace oihana\files ;

use oihana\files\exceptions\DirectoryException;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;

class AssertDirectoryTest extends TestCase
{
    private vfsStreamDirectory $root;

    protected function setUp(): void
    {
        $this->root = vfsStream::setup('testDir', null,
        [
            'validDir' => [],
            'file.txt' => 'This is a file, not a directory',
            'nested' =>
            [
                'anotherDir' => []
            ]
        ]);
    }

    /**
     * @throws DirectoryException
     */
    public function testValidDirectory():void
    {
        $directoryPath = vfsStream::url('testDir/validDir');
        $this->expectNotToPerformAssertions();
        assertDirectory($directoryPath);
    }

    public function testNullDirectoryPath()
    {
        $this->expectException(DirectoryException::class);
        $this->expectExceptionMessage('The directory path must not be null.');

        assertDirectory(null);
    }

    public function testEmptyDirectoryPath()
    {
        $this->expectException(DirectoryException::class);
        $this->expectExceptionMessage('The directory path must not be empty.');

        assertDirectory('');
        assertDirectory('   ');
    }

    public function testPathIsFileNotDirectory()
    {
        $this->expectException(DirectoryException::class);
        $this->expectExceptionMessageMatches('/The path ".+file\.txt" is not a valid directory/');

        $filePath = vfsStream::url('testDir/file.txt');
        assertDirectory($filePath);
    }

    public function testNonExistentDirectory()
    {
        $this->expectException(DirectoryException::class);
        $this->expectExceptionMessageMatches('/The path ".+nonExistentDir" is not a valid directory/');

        $nonExistentPath = vfsStream::url('testDir/nonExistentDir');
        assertDirectory($nonExistentPath);
    }

    public function testUnreadableDirectory()
    {
        // vfsStream ne simule pas parfaitement les permissions, donc nous devrons utiliser le vrai système de fichiers
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $this->markTestSkipped('Permission tests are not reliable on Windows.');
        }

        $tempDir = sys_get_temp_dir() . '/test_unreadable_dir_' . uniqid();
        mkdir($tempDir);
        chmod($tempDir, 0000); // Aucun droit

        $this->expectException(DirectoryException::class);
        $this->expectExceptionMessageMatches('/is not readable/');

        try {
            assertDirectory($tempDir);
        } finally {
            // Restaurer les permissions et nettoyer
            @chmod($tempDir, 0777);
            @rmdir($tempDir);
        }
    }

    public function testNestedDirectory()
    {
        $directoryPath = vfsStream::url('testDir/nested/anotherDir');

        $this->expectNotToPerformAssertions();

        assertDirectory($directoryPath);
    }

    public function testWhitespaceDirectoryPath()
    {
        $this->expectException(DirectoryException::class);
        $this->expectExceptionMessage('The directory path must not be empty.');

        assertDirectory('   ');
    }
}