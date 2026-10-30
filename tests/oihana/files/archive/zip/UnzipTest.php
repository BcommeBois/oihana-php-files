<?php

namespace tests\oihana\files\archive\zip;

use ZipArchive;

use PHPUnit\Framework\TestCase;

use oihana\files\enums\ZipOption;
use oihana\files\exceptions\DirectoryException;
use oihana\files\exceptions\FileException;

use function oihana\files\deleteDirectory;
use function oihana\files\makeDirectory;
use function oihana\files\archive\zip\unzip;

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

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testKeepPermissionsLeavesALinkAlone(): void
    {
        $this->skipWhenSymlinksAreUnreliable() ;

        // chmod() follows a link, so applying the mode stored with a link would change the
        // permissions of the file it points at.

        $archive = $this->makeZipWithLink( 'link.txt' , 'target.txt' , [ 'target.txt' => 'data' ] ) ;

        unzip( $archive , $this->outputDir , [ ZipOption::KEEP_PERMISSIONS => true ] ) ;

        $this->assertTrue( is_link( $this->outputDir . '/link.txt' ) ) ;
        $this->assertSame( 0644 , fileperms( $this->outputDir . '/target.txt' ) & 0o7777 ) ;
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testOverwriteReplacesAnExistingLinkWithoutTouchingItsTarget(): void
    {
        $this->skipWhenSymlinksAreUnreliable() ;

        $archive = $this->makeZipWithLink( 'link.txt' , 'target.txt' , [ 'target.txt' => 'from the archive' ] ) ;

        makeDirectory( $this->outputDir ) ;
        file_put_contents( $this->outputDir . '/elsewhere.txt' , 'untouched' ) ;
        symlink( 'elsewhere.txt' , $this->outputDir . '/link.txt' ) ;

        unzip( $archive , $this->outputDir ) ;

        $this->assertSame( 'target.txt' , readlink( $this->outputDir . '/link.txt' ) ) ;
        $this->assertSame( 'untouched' , file_get_contents( $this->outputDir . '/elsewhere.txt' ) ) ;
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testRefusesALinkEscapingTheDestination(): void
    {
        $this->skipWhenSymlinksAreUnreliable() ;

        $archive = $this->makeZipWithLink( 'link.txt' , '../../escaped.txt' ) ;

        $this->expectException( FileException::class ) ;
        $this->expectExceptionMessageMatches( '/symbolic link to "\.\.\/\.\.\/escaped\.txt", which escapes the destination/' ) ;

        unzip( $archive , $this->outputDir ) ;
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testRefusesALinkToAnAbsolutePath(): void
    {
        $this->skipWhenSymlinksAreUnreliable() ;

        // A link to "/etc" would let every entry written after it out of the destination, and the
        // Zip Slip guard reads entry names only, never an entry's content.

        $archive = $this->makeZipWithLink( 'link.txt' , '/etc/passwd' ) ;

        $this->expectException( FileException::class ) ;
        $this->expectExceptionMessageMatches( '/symbolic link to the absolute path "\/etc\/passwd"/' ) ;

        // The refusal names the option that lifts it, so a caller restoring its own backup is not
        // left guessing.
        $this->expectExceptionMessageMatches( '/Set the "allowUnsafeLinks" option/' ) ;

        unzip( $archive , $this->outputDir ) ;
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testRefusesALinkWithoutATarget(): void
    {
        $this->skipWhenSymlinksAreUnreliable() ;

        $archive = $this->makeZipWithLink( 'link.txt' , '' ) ;

        $this->expectException( FileException::class ) ;
        $this->expectExceptionMessageMatches( '/symbolic link without a target/' ) ;

        unzip( $archive , $this->outputDir ) ;
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testRestoresASymbolicLink(): void
    {
        $this->skipWhenSymlinksAreUnreliable() ;

        $archive = $this->makeZipWithLink( 'sub/link.txt' , 'real.txt' , [ 'sub/real.txt' => 'real' ] ) ;

        $result = unzip( $archive , $this->outputDir ) ;

        $this->assertTrue( $result ) ;
        $this->assertTrue( is_link( $this->outputDir . '/sub/link.txt' ) ) ;
        $this->assertSame( 'real.txt' , readlink( $this->outputDir . '/sub/link.txt' ) ) ;
        $this->assertSame( 'real' , file_get_contents( $this->outputDir . '/sub/link.txt' ) ) ;
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testAllowUnsafeLinksCreatesALinkEscapingTheDestination(): void
    {
        $this->skipWhenSymlinksAreUnreliable() ;

        file_put_contents( $this->tempDir . DIRECTORY_SEPARATOR . 'outside.txt' , 'outside' ) ;

        $archive = $this->makeZipWithLink( 'link.txt' , '../outside.txt' ) ;

        unzip( $archive , $this->outputDir , [ ZipOption::ALLOW_UNSAFE_LINKS => true ] ) ;

        $this->assertTrue( is_link( $this->outputDir . '/link.txt' ) ) ;
        $this->assertSame( '../outside.txt' , readlink( $this->outputDir . '/link.txt' ) ) ;
        $this->assertSame( 'outside' , file_get_contents( $this->outputDir . '/link.txt' ) ) ;
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testAllowUnsafeLinksCreatesALinkToAnAbsolutePath(): void
    {
        $this->skipWhenSymlinksAreUnreliable() ;

        // The case this option exists for: a backup of a tree whose links were absolute on the
        // disk it was taken from, which zip() stores faithfully.

        $absolute = $this->tempDir . DIRECTORY_SEPARATOR . 'uploads' ;
        makeDirectory( $absolute ) ;
        file_put_contents( $absolute . DIRECTORY_SEPARATOR . 'photo.jpg' , 'photo' ) ;

        $archive = $this->makeZipWithLink( 'uploads' , $absolute ) ;

        $result = unzip( $archive , $this->outputDir , [ ZipOption::ALLOW_UNSAFE_LINKS => true ] ) ;

        $this->assertTrue( $result ) ;
        $this->assertTrue( is_link( $this->outputDir . '/uploads' ) ) ;
        $this->assertSame( $absolute , readlink( $this->outputDir . '/uploads' ) ) ;
        $this->assertSame( 'photo' , file_get_contents( $this->outputDir . '/uploads/photo.jpg' ) ) ;
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testAllowUnsafeLinksStillRefusesALinkWithoutATarget(): void
    {
        $this->skipWhenSymlinksAreUnreliable() ;

        // A link to nothing at all is malformed, not a matter of trust: the option does not reach
        // it.

        $archive = $this->makeZipWithLink( 'link.txt' , '' ) ;

        $this->expectException( FileException::class ) ;
        $this->expectExceptionMessageMatches( '/symbolic link without a target/' ) ;

        unzip( $archive , $this->outputDir , [ ZipOption::ALLOW_UNSAFE_LINKS => true ] ) ;
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testThrowsWhenAnEntryCannotBeRead(): void
    {
        // An entry encrypted with AES, read without the password: libzip answers false. Unchecked,
        // that false was written out as an empty file and the extraction reported success — an
        // archive this library cannot read came out as a tree of empty files.

        $archive = $this->tempDir . DIRECTORY_SEPARATOR . 'encrypted.zip' ;
        $zip     = new ZipArchive() ;

        $zip->open( $archive , ZipArchive::CREATE | ZipArchive::OVERWRITE ) ;
        $zip->setPassword( 'secret' ) ;
        $zip->addFromString( 'secret.txt' , 'content' ) ;

        if ( !$zip->setEncryptionName( 'secret.txt' , ZipArchive::EM_AES_256 ) )
        {
            $zip->close() ;

            $this->markTestSkipped( 'This libzip cannot write an AES-encrypted entry.' ) ;
        }

        $zip->close() ;

        $this->expectException( FileException::class ) ;
        $this->expectExceptionMessageMatches( '/Cannot read the entry "secret\.txt" of the zip archive/' ) ;

        unzip( $archive , $this->outputDir ) ;
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testThrowsWhenAnExtractedFileCannotBeWritten(): void
    {
        // The entry is a file and a directory of that name already stands in the way, so the write
        // cannot land. Unchecked, unzip() returned true for a file it had not written.

        $archive = $this->makeZip( [ 'sub' => 'content' ] ) ;

        makeDirectory( $this->outputDir . DIRECTORY_SEPARATOR . 'sub' ) ;

        $this->expectException( FileException::class ) ;
        $this->expectExceptionMessageMatches( '/Cannot write the extracted file ".+sub"/' ) ;

        unzip( $archive , $this->outputDir ) ;
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testThrowsWhenALinkExistsAndOverwriteIsDisabled(): void
    {
        $this->skipWhenSymlinksAreUnreliable() ;

        $archive = $this->makeZipWithLink( 'link.txt' , 'target.txt' , [ 'target.txt' => 'data' ] ) ;

        makeDirectory( $this->outputDir ) ;
        file_put_contents( $this->outputDir . '/link.txt' , 'already here' ) ;

        $this->expectException( FileException::class ) ;
        $this->expectExceptionMessageMatches( '/The target file ".+link\.txt" already exists/' ) ;

        unzip( $archive , $this->outputDir , [ ZipOption::OVERWRITE => false ] ) ;
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testThrowsWhenALinkCannotBeCreated(): void
    {
        $this->skipWhenSymlinksAreUnreliable() ;

        // A directory stands where the link must go: it is not unlinked, and symlink() writes over
        // nothing, so the entry cannot be restored.

        $archive = $this->makeZipWithLink( 'link.txt' , 'target.txt' , [ 'target.txt' => 'data' ] ) ;

        makeDirectory( $this->outputDir . DIRECTORY_SEPARATOR . 'link.txt' ) ;

        $this->expectException( FileException::class ) ;
        $this->expectExceptionMessageMatches( '/Failed to create the symbolic link ".+link\.txt" to "target\.txt"/' ) ;

        unzip( $archive , $this->outputDir ) ;
    }

    /**
     * Builds an archive holding one symbolic link entry — its target as the entry's content and
     * the Unix link type bits in its external attributes, the way zip() stores a link — plus any
     * regular files given.
     *
     * @param array<string,string> $files Map of entry name => content.
     */
    private function makeZipWithLink( string $link , string $target , array $files = [] ): string
    {
        $path = $this->tempDir . DIRECTORY_SEPARATOR . 'with-link.zip' ;
        $zip  = new ZipArchive() ;
        $zip->open( $path , ZipArchive::CREATE | ZipArchive::OVERWRITE ) ;

        foreach ( $files as $entry => $content )
        {
            $zip->addFromString( $entry , $content ) ;
            $zip->setExternalAttributesName( $entry , ZipArchive::OPSYS_UNIX , 0644 << 16 ) ;
        }

        $zip->addFromString( $link , $target ) ;
        $zip->setExternalAttributesName( $link , ZipArchive::OPSYS_UNIX , 0o120777 << 16 ) ;
        $zip->close() ;

        return $path ;
    }

    private function skipWhenSymlinksAreUnreliable(): void
    {
        if ( strtoupper( substr( PHP_OS , 0 , 3 ) ) === 'WIN' )
        {
            $this->markTestSkipped( 'Symbolic link tests are not reliable on Windows.' ) ;
        }
    }
}
