<?php

namespace oihana\files\archive\zip;

use ZipArchive;

use PHPUnit\Framework\TestCase;

use oihana\files\enums\ZipOption;
use oihana\files\exceptions\DirectoryException;
use oihana\files\exceptions\FileException;

use function oihana\files\deleteDirectory;
use function oihana\files\makeDirectory;

class UnzipTest extends TestCase
{
    private string $tempDir ;
    private string $outputDir ;

    /**
     * @throws DirectoryException
     */
    protected function setUp(): void
    {
        $this->tempDir   = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'oihana-php-files/tests/files/archive/unzip-test-' . uniqid() ;
        $this->outputDir = $this->tempDir . DIRECTORY_SEPARATOR . 'out' ;
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
     * Builds a zip archive in the temp dir and returns its path.
     *
     * @param array<string,string> $files   Map of entry name => content.
     * @param string[]              $emptyDirs Directory entries to add.
     */
    private function makeZip( array $files , array $emptyDirs = [] , string $name = 'archive.zip' ): string
    {
        $path = $this->tempDir . DIRECTORY_SEPARATOR . $name ;
        $zip  = new ZipArchive();
        $zip->open( $path , ZipArchive::CREATE | ZipArchive::OVERWRITE );
        foreach ( $files as $entry => $content )
        {
            $zip->addFromString( $entry , $content );
        }
        foreach ( $emptyDirs as $dir )
        {
            $zip->addEmptyDir( $dir );
        }
        $zip->close();
        return $path ;
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testBasicExtraction(): void
    {
        $archive = $this->makeZip( [ 'hello.txt' => 'Hello world' , 'sub/inner.txt' => 'inner' ] );

        $result = unzip( $archive , $this->outputDir );

        $this->assertTrue( $result );
        $this->assertFileExists( $this->outputDir . '/hello.txt' );
        $this->assertSame( 'Hello world' , file_get_contents( $this->outputDir . '/hello.txt' ) );
        $this->assertFileExists( $this->outputDir . '/sub/inner.txt' );
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testExtractsEmptyDirectory(): void
    {
        $archive = $this->makeZip( [ 'keep.txt' => 'k' ] , [ 'empty_dir' ] );

        unzip( $archive , $this->outputDir );

        $this->assertDirectoryExists( $this->outputDir . '/empty_dir' );
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testDryRunReturnsFileEntriesWithoutExtracting(): void
    {
        $archive = $this->makeZip( [ 'a.txt' => 'a' , 'sub/b.txt' => 'b' ] , [ 'empty_dir' ] );

        $entries = unzip( $archive , $this->outputDir , [ ZipOption::DRY_RUN => true ] );

        $this->assertSame( [ 'a.txt' , 'sub/b.txt' ] , $entries );  // directory entries excluded
        $this->assertFileDoesNotExist( $this->outputDir . '/a.txt' );
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testThrowsWhenFileMissing(): void
    {
        $this->expectException( FileException::class );
        unzip( $this->tempDir . '/does-not-exist.zip' , $this->outputDir );
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testThrowsWhenArchiveCannotBeOpened(): void
    {
        // A corrupt .zip exists but ZipArchive::open() fails.
        $bad = $this->tempDir . '/corrupt.zip' ;
        file_put_contents( $bad , 'PK not a real central directory' );

        $this->expectException( FileException::class );
        $this->expectExceptionMessage( 'Cannot open the zip archive' );
        unzip( $bad , $this->outputDir );
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testThrowsOnTooManyEntries(): void
    {
        $archive = $this->makeZip( [ 'a.txt' => 'a' , 'b.txt' => 'b' , 'c.txt' => 'c' ] );

        $this->expectException( FileException::class );
        $this->expectExceptionMessage( 'too many entries' );
        unzip( $archive , $this->outputDir , [ ZipOption::MAX_ENTRIES => 2 ] );
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testThrowsOnExceededSize(): void
    {
        $archive = $this->makeZip( [ 'big.txt' => str_repeat( 'A' , 1000 ) ] );

        $this->expectException( FileException::class );
        $this->expectExceptionMessage( 'maximum extracted size' );
        unzip( $archive , $this->outputDir , [ ZipOption::MAX_SIZE => 100 ] );
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testAllowsSizeWithinLimit(): void
    {
        $archive = $this->makeZip( [ 'small.txt' => 'tiny' ] );

        $this->assertTrue( unzip( $archive , $this->outputDir , [ ZipOption::MAX_SIZE => 1000 ] ) );
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testDetectsZipSlip(): void
    {
        $archive = $this->makeZip( [ '../evil.txt' => 'pwned' ] );

        $this->expectException( FileException::class );
        $this->expectExceptionMessage( 'Zip Slip detected' );
        unzip( $archive , $this->outputDir );
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testThrowsWhenOverwriteDisabledAndFileExists(): void
    {
        $archive = $this->makeZip( [ 'dup.txt' => 'first' ] );

        unzip( $archive , $this->outputDir );                 // first extraction creates the file

        $this->expectException( FileException::class );
        $this->expectExceptionMessage( 'already exists' );
        unzip( $archive , $this->outputDir , [ ZipOption::OVERWRITE => false ] );
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testOverwriteEnabledReplacesFile(): void
    {
        $archive = $this->makeZip( [ 'dup.txt' => 'second' ] );

        unzip( $archive , $this->outputDir );
        $this->assertTrue( unzip( $archive , $this->outputDir ) ); // overwrite defaults to true
        $this->assertSame( 'second' , file_get_contents( $this->outputDir . '/dup.txt' ) );
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testKeepPermissionsRestoresUnixMode(): void
    {
        $archive = $this->tempDir . DIRECTORY_SEPARATOR . 'perms.zip' ;
        $zip     = new ZipArchive();
        $zip->open( $archive , ZipArchive::CREATE | ZipArchive::OVERWRITE );
        $zip->addFromString( 'script.sh' , '#!/bin/sh' );
        $zip->setExternalAttributesName( 'script.sh' , ZipArchive::OPSYS_UNIX , 0750 << 16 );
        $zip->addEmptyDir( 'bin' );
        $zip->setExternalAttributesName( 'bin/' , ZipArchive::OPSYS_UNIX , 0700 << 16 );
        $zip->close();

        unzip( $archive , $this->outputDir , [ ZipOption::KEEP_PERMISSIONS => true ] );

        $this->assertSame( 0750 , fileperms( $this->outputDir . '/script.sh' ) & 0o7777 );
        $this->assertSame( 0700 , fileperms( $this->outputDir . '/bin' ) & 0o7777 );
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testKeepPermissionsLeavesEntriesWithoutUnixModeUntouched(): void
    {
        // No external attributes set: the entry has no Unix mode, so chmod is skipped.
        $archive = $this->makeZip( [ 'plain.txt' => 'data' ] );

        $result = unzip( $archive , $this->outputDir , [ ZipOption::KEEP_PERMISSIONS => true ] );

        $this->assertTrue( $result );
        $this->assertFileExists( $this->outputDir . '/plain.txt' );
    }
}
