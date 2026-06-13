<?php

namespace tests\oihana\files\archive\zip;

use ZipArchive;

use PHPUnit\Framework\TestCase;

use oihana\files\enums\CompressionType;
use oihana\files\exceptions\DirectoryException;
use oihana\files\exceptions\FileException;
use oihana\files\exceptions\UnsupportedCompressionException;
use RuntimeException;

use function oihana\files\deleteDirectory;
use function oihana\files\makeDirectory;
use function oihana\files\archive\zip\zip;

class ZipTest extends TestCase
{
    private string $tempDir ;

    /**
     * @throws DirectoryException
     */
    protected function setUp(): void
    {
        // Ensure the auto-output temp base is absent so the makeDirectory() branch always runs.
        $autoBase = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'oihana/files/archive/zip';
        if ( is_dir( $autoBase ) )
        {
            deleteDirectory( $autoBase );
        }

        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'oihana-php-files/tests/files/archive/zip-test-' . uniqid() ;
        makeDirectory( $this->tempDir );
    }

    /**
     * @throws DirectoryException
     */
    protected function tearDown(): void
    {
        deleteDirectory( $this->tempDir );
    }

    /**
     * @return string[] The list of entry names contained in the archive.
     */
    private function entries( string $archive ): array
    {
        $zip = new ZipArchive();
        $zip->open( $archive );
        $names = [];
        for ( $i = 0 ; $i < $zip->numFiles ; $i++ )
        {
            $names[] = $zip->getNameIndex( $i );
        }
        $zip->close();
        return $names;
    }

    private function makeSourceTree(): string
    {
        $source = $this->tempDir . '/source';
        mkdir( $source );
        file_put_contents( $source . '/file1.txt' , 'content1' );
        mkdir( $source . '/subdir' );
        file_put_contents( $source . '/subdir/file3.ini' , 'config' );
        mkdir( $source . '/empty_dir' );
        return $source;
    }

    /**
     * @throws FileException
     * @throws UnsupportedCompressionException
     * @throws DirectoryException
     */
    public function testCreatesZipFromSingleFileAutoNamed(): void
    {
        $file = $this->tempDir . '/file.txt';
        file_put_contents( $file , 'hello' );

        $archive = zip( $file );

        $this->assertFileExists( $archive );
        $this->assertStringEndsWith( '.zip' , $archive );
        $this->assertSame( [ 'file.txt' ] , $this->entries( $archive ) );

        @unlink( $archive );
    }

    /**
     * @throws FileException
     * @throws UnsupportedCompressionException
     * @throws DirectoryException
     */
    public function testCreatesZipWithExplicitOutput(): void
    {
        $file = $this->tempDir . '/file.txt';
        file_put_contents( $file , 'hello' );

        $output  = $this->tempDir . '/out.zip';
        $archive = zip( $file , $output );

        $this->assertSame( $output , $archive );
        $this->assertFileExists( $output );
    }

    /**
     * @throws FileException
     * @throws UnsupportedCompressionException
     * @throws DirectoryException
     */
    public function testArchivesDirectoryWithBasenamePrefix(): void
    {
        $source  = $this->makeSourceTree();
        $output  = $this->tempDir . '/dir.zip';
        $archive = zip( $source , $output );

        $entries = $this->entries( $archive );

        $this->assertContains( 'source/file1.txt' , $entries );
        $this->assertContains( 'source/subdir/file3.ini' , $entries );
        $this->assertContains( 'source/empty_dir/' , $entries );
    }

    /**
     * @throws FileException
     * @throws UnsupportedCompressionException
     * @throws DirectoryException
     */
    public function testArchivesDirectoryPreservingRoot(): void
    {
        $source  = $this->makeSourceTree();
        $output  = $this->tempDir . '/root.zip';
        $archive = zip( $source , $output , CompressionType::ZIP , $source );

        $entries = $this->entries( $archive );

        $this->assertContains( 'file1.txt' , $entries );
        $this->assertContains( 'subdir/file3.ini' , $entries );
        $this->assertContains( 'empty_dir/' , $entries );
    }

    /**
     * @throws FileException
     * @throws UnsupportedCompressionException
     * @throws DirectoryException
     */
    public function testFileWithPreserveRootKeepsRelativePath(): void
    {
        $source = $this->makeSourceTree();
        $file   = $source . '/subdir/file3.ini';

        $output  = $this->tempDir . '/file-root.zip';
        $archive = zip( $file , $output , CompressionType::ZIP , $source );

        $this->assertSame( [ 'subdir/file3.ini' ] , $this->entries( $archive ) );
    }

    /**
     * @throws FileException
     * @throws UnsupportedCompressionException
     * @throws DirectoryException
     */
    public function testStoredCompression(): void
    {
        $file = $this->tempDir . '/file.txt';
        file_put_contents( $file , str_repeat( 'A' , 1000 ) );

        $output  = $this->tempDir . '/stored.zip';
        $archive = zip( $file , $output , CompressionType::NONE );

        $zip = new ZipArchive();
        $zip->open( $archive );
        $stat = $zip->statIndex( 0 );
        $zip->close();

        $this->assertSame( ZipArchive::CM_STORE , $stat[ 'comp_method' ] );
    }

    /**
     * @throws FileException
     * @throws UnsupportedCompressionException
     * @throws DirectoryException
     */
    public function testArchivesMultipleFiles(): void
    {
        $f1 = $this->tempDir . '/a.txt';
        $f2 = $this->tempDir . '/b.txt';
        file_put_contents( $f1 , 'a' );
        file_put_contents( $f2 , 'b' );

        $output  = $this->tempDir . '/multi.zip';
        $archive = zip( [ $f1 , $f2 ] , $output );

        $this->assertSame( [ 'a.txt' , 'b.txt' ] , $this->entries( $archive ) );
    }

    public function testThrowsRuntimeExceptionOnEmptyPaths(): void
    {
        $this->expectException( RuntimeException::class );
        $this->expectExceptionMessage( 'No input paths provided.' );
        zip( [] );
    }

    public function testThrowsFileExceptionOnMissingPath(): void
    {
        $this->expectException( FileException::class );
        zip( $this->tempDir . '/does-not-exist.txt' );
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testThrowsUnsupportedCompression(): void
    {
        $file = $this->tempDir . '/file.txt';
        file_put_contents( $file , 'hello' );

        $this->expectException( UnsupportedCompressionException::class );
        zip( $file , $this->tempDir . '/bad.zip' , CompressionType::GZIP );
    }

    /**
     * @throws DirectoryException
     * @throws UnsupportedCompressionException
     */
    public function testThrowsFileExceptionWhenOutputCannotBeOpened(): void
    {
        // Opening an existing directory as an archive makes ZipArchive::open() fail.
        $file = $this->tempDir . '/file.txt';
        file_put_contents( $file , 'hello' );

        $this->expectException( FileException::class );
        $this->expectExceptionMessage( 'Cannot create the zip archive' );
        zip( $file , $this->tempDir );
    }

    /**
     * @throws DirectoryException
     * @throws UnsupportedCompressionException
     */
    public function testThrowsFileExceptionWhenArchiveCannotBeFlushed(): void
    {
        // A missing parent directory lets open() succeed but makes close() fail to write the file.
        $file = $this->tempDir . '/file.txt';
        file_put_contents( $file , 'hello' );

        $this->expectException( FileException::class );
        $this->expectExceptionMessage( 'Cannot create the zip archive' );
        zip( $file , $this->tempDir . '/missing-dir/out.zip' );
    }

    /**
     * @throws DirectoryException
     * @throws UnsupportedCompressionException
     * @throws FileException
     */
    public function testThrowsRuntimeExceptionWhenNoFilesAdded(): void
    {
        // preserveRoot equal to the single file yields an empty archive path -> nothing added.
        $file = $this->tempDir . '/file.txt';
        file_put_contents( $file , 'hello' );

        $this->expectException( RuntimeException::class );
        $this->expectExceptionMessage( 'No files were added to the archive.' );
        zip( $file , $this->tempDir . '/empty.zip' , CompressionType::ZIP , $file );
    }
}
