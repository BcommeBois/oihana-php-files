<?php

namespace tests\oihana\files\archive\tar;

use oihana\files\enums\CompressionType;
use oihana\files\enums\TarInfo;
use oihana\files\exceptions\DirectoryException;
use oihana\files\exceptions\FileException;
use Phar;
use PharData;
use PHPUnit\Framework\TestCase;
use function oihana\files\archive\tar\tarFileInfo;
use function oihana\files\deleteDirectory;
use function oihana\files\makeDirectory;

class TarFileInfoTest extends TestCase
{
    private string $tempDir;

    /**
     * @throws DirectoryException
     */
    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/tar_info_' . uniqid();
        makeDirectory( $this->tempDir );
    }

    /**
     * @throws DirectoryException
     */
    protected function tearDown(): void
    {
        deleteDirectory($this->tempDir);
    }

    public function testThrowsExceptionForMissingFile(): void
    {
        $this->expectException(FileException::class);
        tarFileInfo($this->tempDir . '/does-not-exist.tar');
    }

    /**
     * @throws FileException
     */
    public function testReturnsFalseForNonTarFile(): void
    {
        $file = $this->tempDir . '/fake.txt';
        file_put_contents($file, 'not a tar');

        $info = tarFileInfo($file);

        $this->assertFalse($info[TarInfo::IS_VALID]);
        $this->assertSame('txt', $info[TarInfo::EXTENSION]);
        $this->assertArrayHasKey(TarInfo::MIME_TYPE, $info);
    }

    /**
     * @throws FileException
     */
    public function testReturnsValidInfoForValidTar(): void
    {
        $file = $this->tempDir . '/valid.tar';
        $dataFile = $this->tempDir . '/data.txt';
        file_put_contents($dataFile, '12345678');

        $tar = new PharData($file);
        $tar->addFile($dataFile, 'data.txt');

        $info = tarFileInfo($file);

        $this->assertTrue($info[TarInfo::IS_VALID]);
        $this->assertSame('tar', $info[TarInfo::EXTENSION]);
        $this->assertSame(CompressionType::NONE, $info[TarInfo::COMPRESSION]);
        $this->assertSame(1, $info[TarInfo::FILE_COUNT]);
        $this->assertSame(8, $info[TarInfo::TOTAL_SIZE]);
    }

    /**
     * @throws FileException
     */
    public function testHandlesCompressedTarGz(): void
    {
        $file   = $this->tempDir . '/archive.tar';
        $gzFile = $this->tempDir . '/archive.tar.gz';

        file_put_contents($this->tempDir . '/file.txt', 'hello');

        $tar = new PharData( $file ) ;
        $tar->addFile($this->tempDir . '/file.txt', 'file.txt');
        $tar->compress( Phar::GZ ) ;

        // Remove uncompressed to keep only .gz
        unlink( $file ) ;

        $info = tarFileInfo( $gzFile , true );

        $this->assertTrue( $info[ TarInfo::IS_VALID ] ); // PharData can't read .gz
        $this->assertSame('gz', $info[TarInfo::EXTENSION]);
        $this->assertSame(CompressionType::GZIP, $info[TarInfo::COMPRESSION]);
        $this->assertEquals( 1 , $info[TarInfo::FILE_COUNT]);
    }
}