<?php

namespace tests\oihana\files\archive\zip;

use ZipArchive;

use PHPUnit\Framework\TestCase;

use oihana\files\exceptions\DirectoryException;
use oihana\files\exceptions\FileException;

use function oihana\files\archive\zip\assertZip;
use function oihana\files\deleteDirectory;

class AssertZipTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/zip_test_' . uniqid();
        mkdir( $this->tempDir , 0777 , true );
    }

    /**
     * @throws DirectoryException
     */
    protected function tearDown(): void
    {
        deleteDirectory( $this->tempDir );
    }

    private function makeValidZip( string $name = 'valid.zip' ): string
    {
        $path = $this->tempDir . '/' . $name;
        $zip  = new ZipArchive();
        $zip->open( $path , ZipArchive::CREATE );
        $zip->addFromString( 'hello.txt' , 'hello' );
        $zip->close();
        return $path;
    }

    public function testThrowsFileExceptionOnMissingFile(): void
    {
        $this->expectException( FileException::class );
        assertZip( $this->tempDir . '/missing.zip' );
    }

    /**
     * @throws FileException
     */
    public function testReturnsFalseOnInvalidExtension(): void
    {
        $file = $this->tempDir . '/invalid.txt';
        file_put_contents( $file , 'not a zip' );
        $this->assertFalse( assertZip( $file ) );
    }

    /**
     * @throws FileException
     */
    public function testReturnsFalseOnInvalidMime(): void
    {
        $file = $this->tempDir . '/fake.zip';
        file_put_contents( $file , 'not a real zip content' );
        $this->assertFalse( assertZip( $file ) );
    }

    /**
     * @throws FileException
     */
    public function testReturnsTrueOnValidZipFile(): void
    {
        $this->assertTrue( assertZip( $this->makeValidZip() ) );
    }

    /**
     * @throws FileException
     */
    public function testStrictModeSucceedsOnValidZip(): void
    {
        $this->assertTrue( assertZip( $this->makeValidZip( 'strict.zip' ) , strictMode: true ) );
    }
}
