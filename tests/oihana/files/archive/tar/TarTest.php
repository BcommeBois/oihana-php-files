<?php

namespace tests\oihana\files\archive\tar;

use oihana\files\enums\CompressionType;
use oihana\files\exceptions\DirectoryException;
use oihana\files\exceptions\FileException;
use oihana\files\exceptions\UnsupportedCompressionException;
use PharData;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function oihana\files\archive\tar\tar;
use function oihana\files\deleteDirectory;
use function oihana\files\makeDirectory;
use function oihana\reflect\helpers\getFunctionInfo;

class TarTest extends TestCase
{
    private ?string $archivePath = null ;
    private ?string $tempDir     = null ;

    /**
     * @throws DirectoryException
     */
    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/oihana-php-files/tests/files/archive/tar-test-' . uniqid() ;
        makeDirectory( $this->tempDir );
    }

    /**
     * @throws DirectoryException
     */
    protected function tearDown(): void
    {
        deleteDirectory( $this->tempDir );
        if( is_string( $this->archivePath ) && file_exists( $this->archivePath ) )
        {
            @unlink( $this->archivePath );
        }
    }

    /**
     * @throws FileException
     * @throws UnsupportedCompressionException
     * @throws DirectoryException
     */
    public function testCreatesTarWithoutCompression(): void
    {
        $file = $this->tempDir . '/file.txt';

        file_put_contents( $file, 'test content' );

        $this->archivePath = tar( $file , null, CompressionType::NONE ) ;

        $this->assertFileExists( $this->archivePath );
        $this->assertStringEndsWith('.tar' , $this->archivePath );
    }

    /**
     * @return void
     * @throws DirectoryException
     * @throws FileException
     * @throws UnsupportedCompressionException
     */
    public function testCreatesTarWithGzipCompression(): void
    {
        $file = $this->tempDir . '/file.txt';

        file_put_contents( $file , 'test' ) ;

        $this->archivePath = tar( $file ); // default arguments -> , null, CompressionType::GZIP

        $this->assertFileExists( $this->archivePath ) ;
        $this->assertStringEndsWith('.tar.gz' , $this->archivePath );
    }

    /**
     * @return void
     * @throws DirectoryException
     * @throws FileException
     * @throws UnsupportedCompressionException
     */
    public function testCreatesTarWithPreserveRoot(): void
    {
        $subDir = $this->tempDir . '/sub';

        mkdir( $subDir );

        file_put_contents($subDir . '/file.txt' , 'rooted' ) ;

        $this->archivePath = tar( $subDir , null , CompressionType::NONE, $subDir ) ;

        $this->assertFileExists( $this->archivePath );
    }

    /**
     * @throws UnsupportedCompressionException
     * @throws DirectoryException
     */
    public function testThrowsExceptionIfFileDoesNotExist(): void
    {
        $this->expectException(FileException::class);

        tar($this->tempDir . '/missing.txt');
    }

    /**
     * @return void
     * @throws DirectoryException
     * @throws FileException
     * @throws UnsupportedCompressionException
     */
    public function testThrowsExceptionIfPathsArrayIsEmpty(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("No input paths provided.");
        tar( [] ) ;
    }

    /**
     * @return void
     * @throws DirectoryException
     * @throws FileException
     * @throws UnsupportedCompressionException
     */
    public function testCreatesTarWithOutputPath(): void
    {
        $file = $this->tempDir . '/out.txt';

        file_put_contents( $file , 'data' ) ;

        $outputPath = $this->tempDir . '/my-archive.tar.gz';

        $this->archivePath = tar( $file , $outputPath );

        $this->assertFileExists( $this->archivePath );
        $this->assertSame( $outputPath, $this->archivePath  );
    }

    /**
     * @return void
     * @throws DirectoryException
     * @throws FileException
     * @throws UnsupportedCompressionException
     */
    public function testThrowsIfCompressionNotSupported(): void
    {
        $this->expectException( UnsupportedCompressionException::class ) ;
        tar( $this->tempDir , null, 'unsupported-compression-type' ) ;
    }

    /**
     * @return void
     * @throws DirectoryException
     * @throws FileException
     * @throws UnsupportedCompressionException
     */
    public function testIncludesEmptyDirectoryInArchive(): void
    {
        $emptyDir = $this->tempDir . '/empty-folder';

        mkdir( $emptyDir );

        $this->archivePath = tar( $this->tempDir , null , CompressionType::NONE ) ;

        deleteDirectory( $emptyDir );

        $this->assertFileExists( $this->archivePath ) ;

        $extractDir = $this->tempDir . '/extracted';
        mkdir($extractDir);

        $phar = new PharData( $this->archivePath ) ;

        $expectedKey = basename( $this->tempDir ) . '/empty-folder';

        $this->assertTrue
        (
            $phar->offsetExists($expectedKey),
            "The archive should contain the empty directory '$expectedKey'."
        );

        $this->assertTrue
        (
            $phar[ $expectedKey ]->isDir() ,
            "The entry '$expectedKey' should be a directory."
        );
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     * @throws UnsupportedCompressionException
     */
    public function testCreatesTarFromDirectoryWithoutPreserveRoot(): void
    {
        // Directory (no preserveRoot) -> the basename-prefixed branch adds the file.
        $sub = $this->tempDir . '/payload';
        mkdir($sub);
        file_put_contents($sub . '/inner.txt', 'inner');

        $this->archivePath = tar($sub, null, CompressionType::NONE);

        $this->assertFileExists($this->archivePath);

        $phar = new PharData($this->archivePath);
        $this->assertTrue($phar->offsetExists('payload/inner.txt'));
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     * @throws UnsupportedCompressionException
     */
    public function testCreatesTarFromFileWithPreserveRoot(): void
    {
        // Single file + preserveRoot -> the str_replace(preserveRoot) archive-path branch.
        $file = $this->tempDir . '/rooted.txt';
        file_put_contents($file, 'data');

        $this->archivePath = tar($file, null, CompressionType::NONE, $this->tempDir);

        $this->assertFileExists($this->archivePath);

        $phar = new PharData($this->archivePath);
        $this->assertTrue($phar->offsetExists('rooted.txt'));
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     * @throws UnsupportedCompressionException
     */
    public function testThrowsWhenFileEqualsPreserveRoot(): void
    {
        // When the only path IS the preserveRoot, its archive path is empty -> no content.
        $file = $this->tempDir . '/self.txt';
        file_put_contents($file, 'x');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No files were added to the archive.');

        tar($file, $this->tempDir . '/out.tar', CompressionType::NONE, $file);
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     * @throws UnsupportedCompressionException
     */
    public function testCreatesTemporaryWorkingDirectoryWhenMissing(): void
    {
        // Remove the function's cached temp working dir so the makeDirectory() branch runs.
        $name    = getFunctionInfo('oihana\files\archive\tar\tar')['name'];
        $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR
                 . str_replace('\\', DIRECTORY_SEPARATOR, $name) . DIRECTORY_SEPARATOR;

        if (is_dir($tmpPath))
        {
            deleteDirectory($tmpPath, assertable: false);
        }

        $file = $this->tempDir . '/x.txt';
        file_put_contents($file, 'data');

        $this->archivePath = tar($file, null, CompressionType::NONE);

        $this->assertFileExists($this->archivePath);
    }
}