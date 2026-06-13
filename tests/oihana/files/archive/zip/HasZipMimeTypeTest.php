<?php

namespace tests\oihana\files\archive\zip;

use ZipArchive;

use PHPUnit\Framework\TestCase;

use oihana\files\exceptions\DirectoryException;

use function oihana\files\archive\zip\hasZipMimeType;
use function oihana\files\deleteDirectory;

class HasZipMimeTypeTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/zip_test_' . uniqid();
        mkdir( $this->tmpDir );
    }

    /**
     * @throws DirectoryException
     */
    protected function tearDown(): void
    {
        deleteDirectory( $this->tmpDir );
    }

    private function createZipFile( string $filename ): string
    {
        $filePath = $this->tmpDir . '/' . $filename;
        $zip = new ZipArchive();
        $zip->open( $filePath , ZipArchive::CREATE );
        $zip->addFromString( 'hello.txt' , 'hello world' );
        $zip->close();
        return $filePath;
    }

    private function createTempFile( string $filename , string $content ): string
    {
        $filePath = $this->tmpDir . '/' . $filename;
        file_put_contents( $filePath , $content );
        return $filePath;
    }

    public function testRecognizedZipMimeType(): void
    {
        $zipFile = $this->createZipFile( 'test.zip' );
        $this->assertTrue( hasZipMimeType( $zipFile ) , 'Should detect a real zip archive as zip-related' );
    }

    public function testUnrecognizedMimeType(): void
    {
        $txtFile = $this->createTempFile( 'test.txt' , 'plain text file' );
        $this->assertFalse( hasZipMimeType( $txtFile ) , 'Should not detect a plain text file as zip-related' );
    }

    public function testNonExistentFile(): void
    {
        $this->assertFalse( hasZipMimeType( $this->tmpDir . '/nonexistent.file' ) , 'Should return false if the file does not exist' );
    }

    public function testCustomMimeTypes(): void
    {
        $customMimeTypes = [ 'text/plain' ];
        $txtFile = $this->createTempFile( 'custom.txt' , 'this is plain text' );
        $this->assertTrue( hasZipMimeType( $txtFile , $customMimeTypes ) , 'Should match using a custom mime type list' );
    }
}
