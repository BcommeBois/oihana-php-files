<?php

namespace oihana\files ;

use oihana\files\exceptions\DirectoryException;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;

class AssertWritableDirectoryTest extends TestCase
{
    private vfsStreamDirectory $root;

    protected function setUp(): void
    {
        $this->root = vfsStream::setup('testDir', null,
        [
            'validDir' => [],
            'file.txt' => 'This is a file',
            'readOnlyDir' => [],
            'nested' => [
                'writableDir' => []
            ]
        ]);
    }
    public function testValidWritableDirectory():void
    {
        $directoryPath = vfsStream::url('testDir/validDir');

        // Cette fonction devrait réussir sans lever d'exception
        $this->expectNotToPerformAssertions();
        assertWritableDirectory($directoryPath);
    }

    public function testNullDirectoryPath():void
    {
        $this->expectException(DirectoryException::class);
        $this->expectExceptionMessage('The directory path must not be null.');

        assertWritableDirectory(null);
    }

    public function testEmptyDirectoryPath():void
    {
        $this->expectException(DirectoryException::class);
        $this->expectExceptionMessage('The directory path must not be empty.');

        assertWritableDirectory('');
        assertWritableDirectory('   ');
    }

    public function testPathIsFileNotDirectory():void
    {
        $this->expectException(DirectoryException::class);
        $this->expectExceptionMessageMatches('/The path ".+file\.txt" is not a valid directory/');

        $filePath = vfsStream::url('testDir/file.txt');
        assertWritableDirectory($filePath);
    }

    public function testNonExistentDirectory():void
    {
        $this->expectException(DirectoryException::class);
        $this->expectExceptionMessageMatches('/The path ".+nonExistentDir" is not a valid directory/');

        $nonExistentPath = vfsStream::url('testDir/nonExistentDir');
        assertWritableDirectory($nonExistentPath);
    }

    public function testReadOnlyDirectory():void
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $this->markTestSkipped('Permission tests are not reliable on Windows.');
        }

        $tempDir = sys_get_temp_dir() . '/test_readonly_dir_' . uniqid();
        mkdir($tempDir);
        chmod($tempDir, 0555); // Lecture et exécution seulement (pas d'écriture)

        $this->expectException(DirectoryException::class);
        $this->expectExceptionMessageMatches('/The directory ".+" is not writable/');

        try {
            // Vérifier que le répertoire existe et n'est pas accessible en écriture
            $this->assertTrue(is_dir($tempDir));
            $this->assertFalse(is_writable($tempDir));

            assertWritableDirectory($tempDir);
        } finally {
            @chmod($tempDir, 0777);
            @rmdir($tempDir);
        }
    }

    /**
     * @throws DirectoryException
     */
    public function testNestedWritableDirectory():void
    {
        $directoryPath = vfsStream::url('testDir/nested/writableDir');

        // Cette fonction devrait réussir sans lever d'exception
        $this->expectNotToPerformAssertions();
        assertWritableDirectory($directoryPath);
    }

    /**
     * @throws DirectoryException
     */
    public function testDirectoryWithSpacesInName():void
    {
        // Créer un répertoire avec des espaces dans le nom
        vfsStream::create([
            'dir with spaces' => []
        ], $this->root);

        $directoryPath = vfsStream::url('testDir/dir with spaces');

        $this->expectNotToPerformAssertions();
        assertWritableDirectory($directoryPath);
    }

    public function testDirectoryNotReadable():void
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $this->markTestSkipped('Permission tests are not reliable on Windows.');
        }

        $tempDir = sys_get_temp_dir() . '/test_unreadable_dir_' . uniqid();
        mkdir($tempDir);
        chmod($tempDir, 0222); // Écriture seulement (pas de lecture)

        $this->expectException(DirectoryException::class);
        $this->expectExceptionMessageMatches('/The directory ".+" is not readable/');

        try
        {
            assertWritableDirectory($tempDir);
        } finally {
            @chmod($tempDir, 0777);
            @rmdir($tempDir);
        }
    }
}