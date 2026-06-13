<?php

namespace tests\oihana\files\archive\zip;

use ZipArchive;

use PHPUnit\Framework\TestCase;

use oihana\files\enums\CompressionType;
use oihana\files\enums\ZipInfo;
use oihana\files\exceptions\DirectoryException;
use oihana\files\exceptions\FileException;

use function oihana\files\archive\zip\zipFileInfo;
use function oihana\files\deleteDirectory;
use function oihana\files\makeDirectory;

class ZipFileInfoTest extends TestCase
{
    private string $tempDir;

    /**
     * @throws DirectoryException
     */
    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/zip_info_' . uniqid();
        makeDirectory( $this->tempDir );
    }

    /**
     * @throws DirectoryException
     */
    protected function tearDown(): void
    {
        deleteDirectory( $this->tempDir );
    }

    public function testThrowsExceptionForMissingFile(): void
    {
        $this->expectException( FileException::class );
        zipFileInfo( $this->tempDir . '/does-not-exist.zip' );
    }

    /**
     * @throws FileException
     */
    public function testReturnsFalseForNonZipFile(): void
    {
        $file = $this->tempDir . '/fake.txt';
        file_put_contents( $file , 'not a zip' );

        $info = zipFileInfo( $file );

        $this->assertFalse( $info[ ZipInfo::IS_VALID ] );
        $this->assertSame( 'txt' , $info[ ZipInfo::EXTENSION ] );
        $this->assertSame( CompressionType::NONE , $info[ ZipInfo::COMPRESSION ] );
        $this->assertNull( $info[ ZipInfo::FILE_COUNT ] );
        $this->assertNull( $info[ ZipInfo::TOTAL_SIZE ] );
        $this->assertArrayHasKey( ZipInfo::MIME_TYPE , $info );
    }

    /**
     * @throws FileException
     */
    public function testReturnsValidInfoForValidZip(): void
    {
        $file = $this->tempDir . '/valid.zip';
        $zip  = new ZipArchive();
        $zip->open( $file , ZipArchive::CREATE );
        $zip->addFromString( 'data.txt' , '12345678' );
        $zip->close();

        $info = zipFileInfo( $file );

        $this->assertTrue( $info[ ZipInfo::IS_VALID ] );
        $this->assertSame( 'zip' , $info[ ZipInfo::EXTENSION ] );
        $this->assertSame( CompressionType::ZIP , $info[ ZipInfo::COMPRESSION ] );
        $this->assertSame( 1 , $info[ ZipInfo::FILE_COUNT ] );
        $this->assertSame( 8 , $info[ ZipInfo::TOTAL_SIZE ] );
    }

    /**
     * @throws FileException
     */
    public function testReturnsValidInfoInStrictMode(): void
    {
        $file = $this->tempDir . '/strict.zip';
        $zip  = new ZipArchive();
        $zip->open( $file , ZipArchive::CREATE );
        $zip->addFromString( 'a.txt' , 'abc' );
        $zip->addFromString( 'b.txt' , 'de' );
        $zip->close();

        $info = zipFileInfo( $file , true );

        $this->assertTrue( $info[ ZipInfo::IS_VALID ] );
        $this->assertSame( 2 , $info[ ZipInfo::FILE_COUNT ] );
        $this->assertSame( 5 , $info[ ZipInfo::TOTAL_SIZE ] );
    }
}
