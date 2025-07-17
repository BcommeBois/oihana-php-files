<?php

namespace oihana\files\archive\tar;

use oihana\files\enums\CompressionType;
use oihana\files\exceptions\DirectoryException;
use oihana\files\exceptions\FileException;
use oihana\files\exceptions\UnsupportedCompressionException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function oihana\files\deleteDirectory;
use function oihana\files\makeDirectory;

class TarTest extends TestCase
{
    private string $tempDir;

    /**
     * @throws DirectoryException
     */
    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/tar_test_' . uniqid();
        makeDirectory( $this->tempDir );
    }

    /**
     * @throws DirectoryException
     */
    protected function tearDown(): void
    {
        deleteDirectory($this->tempDir);
    }

    /**
     * @throws FileException|UnsupportedCompressionException
     */
    public function testCreatesTarWithoutCompression(): void
    {
        $file = $this->tempDir . '/file.txt';
        file_put_contents($file, 'test content');

        $archivePath = tar($file, null, CompressionType::NONE);

        $this->assertFileExists($archivePath);
        $this->assertStringEndsWith('.tar', $archivePath);
    }

    /**
     * @throws FileException|UnsupportedCompressionException
     */
    public function testCreatesTarWithGzipCompression(): void
    {
        $file = $this->tempDir . '/file.txt';
        file_put_contents($file, 'test');

        $archivePath = tar($file, null, CompressionType::GZIP);

        $this->assertFileExists($archivePath);
        $this->assertStringEndsWith('.tar.gz', $archivePath);
    }

    /**
     * @throws FileException|UnsupportedCompressionException
     */
    public function testCreatesTarWithPreserveRoot(): void
    {
        $subDir = $this->tempDir . '/sub';

        mkdir( $subDir );

        file_put_contents($subDir . '/file.txt' , 'rooted' ) ;

        $archivePath = tar( $subDir , null , CompressionType::NONE, $subDir ) ;

        $this->assertFileExists($archivePath);
    }

    public function testThrowsExceptionIfFileDoesNotExist(): void
    {
        $this->expectException(FileException::class);

        tar($this->tempDir . '/missing.txt');
    }

    /**
     * @throws FileException|UnsupportedCompressionException
     */
    public function testThrowsExceptionIfPathsArrayIsEmpty(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("No input paths provided.");

        tar([]);
    }

    /**
     * @throws FileException|UnsupportedCompressionException
     */
    public function testCreatesTarWithOutputPath(): void
    {
        $file = $this->tempDir . '/out.txt';
        file_put_contents($file, 'data');

        $outputPath = $this->tempDir . '/my-archive.tar.gz';

        $result = tar($file, $outputPath, CompressionType::GZIP);

        $this->assertFileExists($result);
        $this->assertSame($outputPath, $result);
    }

    /**
     * @throws FileException
     */
    public function testThrowsIfCompressionNotSupported(): void
    {
        $this->expectException( UnsupportedCompressionException::class ) ;
        tar( $this->tempDir , null, 'unsupported-compression-type' ) ;
    }
}