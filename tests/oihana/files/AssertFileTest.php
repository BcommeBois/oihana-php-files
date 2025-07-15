<?php

namespace oihana\files ;

use oihana\files\exceptions\FileException;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;

class AssertFileTest extends TestCase
{
    private vfsStreamDirectory $root;

    protected function setUp(): void
    {
        $this->root = vfsStream::setup('testDir', null,
        [
            'validFile.txt' => 'This is a valid file',
            'emptyFile.txt' => '',
            'subdir' =>
            [
                'anotherFile.txt' => 'Another file content',
                'nestedSubdir' => []
            ],
            'notAFile' => [],
            'readme.md' => '# Test File'
        ]);

        vfsStream::create([
            'file with spaces.txt' => 'Content with spaces in filename'
        ] , $this->root ) ;
    }

    /**
     * @throws FileException
     */
    public function testValidFile():void
    {
        $filePath = vfsStream::url('testDir/validFile.txt');
        $this->expectNotToPerformAssertions();
        assertFile($filePath);
    }

    /**
     * @throws FileException
     */
    public function testEmptyFile():void
    {
        $filePath = vfsStream::url('testDir/emptyFile.txt');
        $this->expectNotToPerformAssertions();
        assertFile($filePath);
    }

    public function testNullFilePath():void
    {
        $this->expectException(FileException::class);
        $this->expectExceptionMessage('The file path must not be null.');
        assertFile(null);
    }

    public function testEmptyFilePath():void
    {
        $this->expectException(FileException::class);
        $this->expectExceptionMessage('The file path must not be empty.');

        assertFile('');
        assertFile('   ');
    }

    public function testPathIsDirectoryNotFile():void
    {
        $this->expectException(FileException::class);
        $this->expectExceptionMessageMatches('/The file path ".+notAFile" is not a valid file/');

        $dirPath = vfsStream::url('testDir/notAFile');
        assertFile($dirPath);
    }

    public function testNonExistentFile():void
    {
        $this->expectException(FileException::class);
        $this->expectExceptionMessageMatches('/The file path ".+nonExistentFile\.txt" is not a valid file/');

        $nonExistentPath = vfsStream::url('testDir/nonExistentFile.txt');
        assertFile($nonExistentPath);
    }

    public function testUnreadableFile():void
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $this->markTestSkipped('Permission tests are not reliable on Windows.');
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'test_unreadable_file_');
        file_put_contents($tempFile, 'test content');
        chmod($tempFile, 0000); // Aucun droit

        $this->expectException(FileException::class);
        $this->expectExceptionMessageMatches('/is not readable/');

        try {
            assertFile($tempFile);
        } finally {
            @chmod($tempFile, 0666);
            @unlink($tempFile);
        }
    }

    /**
     * @throws FileException
     */
    public function testNestedFile():void
    {
        $filePath = vfsStream::url('testDir/subdir/anotherFile.txt');
        $this->expectNotToPerformAssertions();
        assertFile($filePath);
    }

    public function testWhitespaceFilePath():void
    {
        $this->expectException(FileException::class);
        $this->expectExceptionMessage('The file path must not be empty.');
        assertFile('   ');
    }

    /**
     * @throws FileException
     */
    public function testFileWithSpacesInName():void
    {
        $filePath = vfsStream::url('testDir/file with spaces.txt');
        $this->expectNotToPerformAssertions();
        assertFile($filePath);
    }

    /**
     * @throws FileException
     */
    public function testFileWithDifferentExtensions()
    {
        $txtFilePath = vfsStream::url('testDir/validFile.txt');
        $mdFilePath = vfsStream::url('testDir/readme.md');

        $this->expectNotToPerformAssertions();
        assertFile($txtFilePath);
        assertFile($mdFilePath);
    }

    /**
     * @throws FileException
     */
    public function testVeryLongFilePath()
    {
        // Créez un chemin de fichier très long (mais toujours dans les limites du système)
        $longPath = 'a/very/long/path/to/a/file/that/is/nested/many/levels/deep/';
        $longPath .= str_repeat('long/', 10); // Ajoute 10 niveaux supplémentaires
        $longPath .= 'finalfile.txt';

        // Ajoutez ce fichier à notre système de fichiers virtuel
        vfsStream::create([
            $longPath => 'Content in a very long path'
        ], $this->root);

        $filePath = vfsStream::url('testDir/' . $longPath);
        $this->expectNotToPerformAssertions();
        assertFile($filePath);
    }
}