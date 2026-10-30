<?php

namespace tests\oihana\files\archive\zip;

use ZipArchive;

use PHPUnit\Framework\TestCase;

use oihana\files\enums\CompressionType;
use oihana\files\enums\ZipOption;
use oihana\files\exceptions\DirectoryException;
use oihana\files\exceptions\FileException;
use oihana\files\exceptions\UnsupportedCompressionException;
use RuntimeException;

use function oihana\files\deleteDirectory;
use function oihana\files\makeDirectory;
use function oihana\files\archive\zip\unzip;
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
     * Regression guard for the `@$zip->open()` suppression: targeting an archive path
     * *under an existing file* fails with `ENOTDIR`, which makes `ZipArchive::open()`
     * emit a native `No such file or directory` warning before the typed exception.
     * Without the `@`, this test fails under `failOnWarning=true`.
     *
     * @throws DirectoryException
     * @throws UnsupportedCompressionException
     */
    public function testThrowsFileExceptionWhenArchivePathIsUnderAFile(): void
    {
        $file = $this->tempDir . '/file.txt';
        file_put_contents( $file , 'hello' );

        $this->expectException( FileException::class );
        $this->expectExceptionMessage( 'Cannot create the zip archive' );
        zip( $file , $file . '/out.zip' );
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

    /**
     * @throws DirectoryException
     * @throws FileException
     * @throws UnsupportedCompressionException
     */
    public function testStoresADanglingLinkInsteadOfFailing(): void
    {
        $this->skipWhenSymlinksAreUnreliable() ;

        // getRealPath() answers false for a link pointing at nothing, and ZipArchive::addFile()
        // refuses false with a ValueError — not a FileException, so no caller could catch it, and
        // archiving a tree holding one broken link stopped the program.

        $source = $this->tempDir . DIRECTORY_SEPARATOR . 'tree' ;
        makeDirectory( $source ) ;
        file_put_contents( $source . '/real.txt' , 'real' ) ;
        symlink( 'no-such-target' , $source . '/dangling' ) ;

        $archive = zip( $source , $this->tempDir . '/dangling.zip' , preserveRoot: $source ) ;

        $this->assertSame( 0o120000 , $this->entryTypeBits( $archive , 'dangling' ) ) ;
        $this->assertSame( 'no-such-target' , $this->entryContent( $archive , 'dangling' ) ) ;
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     * @throws UnsupportedCompressionException
     */
    public function testStoresALinkToADirectoryWithoutReadingIt(): void
    {
        $this->skipWhenSymlinksAreUnreliable() ;

        $source  = $this->tempDir . DIRECTORY_SEPARATOR . 'tree' ;
        $outside = $this->tempDir . DIRECTORY_SEPARATOR . 'outside' ;

        makeDirectory( $source ) ;
        makeDirectory( $outside ) ;
        file_put_contents( $source . '/real.txt' , 'real' ) ;
        file_put_contents( $outside . '/secret.txt' , 'secret' ) ;
        symlink( $outside , $source . '/linked' ) ;

        $archive = zip( $source , $this->tempDir . '/linked-dir.zip' , preserveRoot: $source ) ;

        // The link is one entry, and what it points at is nowhere: the archive used to hold an
        // empty directory in its place, and the content it pointed at was lost without a word.

        $this->assertSame( [ 'linked' , 'real.txt' ] , $this->entryNames( $archive ) ) ;
        $this->assertSame( 0o120000 , $this->entryTypeBits( $archive , 'linked' ) ) ;
        $this->assertSame( $outside , $this->entryContent( $archive , 'linked' ) ) ;
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     * @throws UnsupportedCompressionException
     */
    public function testStoresALinkToAFileAsALinkAndNotAsItsTarget(): void
    {
        $this->skipWhenSymlinksAreUnreliable() ;

        $source = $this->tempDir . DIRECTORY_SEPARATOR . 'tree' ;
        makeDirectory( $source ) ;
        file_put_contents( $source . '/real.txt' , 'real content' ) ;
        symlink( 'real.txt' , $source . '/link.txt' ) ;

        $archive = zip( $source , $this->tempDir . '/linked-file.zip' , preserveRoot: $source ) ;

        // The entry holds the target as a path, not the twelve bytes it points at.
        $this->assertSame( 0o120000 , $this->entryTypeBits( $archive , 'link.txt' ) ) ;
        $this->assertSame( 'real.txt' , $this->entryContent( $archive , 'link.txt' ) ) ;
        $this->assertSame( 'real content' , $this->entryContent( $archive , 'real.txt' ) ) ;
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     * @throws UnsupportedCompressionException
     */
    public function testALinkSurvivesTheRoundTrip(): void
    {
        $this->skipWhenSymlinksAreUnreliable() ;

        $source = $this->tempDir . DIRECTORY_SEPARATOR . 'tree' ;
        makeDirectory( $source . DIRECTORY_SEPARATOR . 'sub' ) ;
        file_put_contents( $source . '/sub/real.txt' , 'real' ) ;
        symlink( 'sub/real.txt' , $source . '/link.txt' ) ;

        $archive = zip( $source , $this->tempDir . '/round.zip' , preserveRoot: $source ) ;
        $back    = $this->tempDir . DIRECTORY_SEPARATOR . 'back' ;

        unzip( $archive , $back ) ;

        $this->assertTrue( is_link( $back . '/link.txt' ) ) ;
        $this->assertSame( 'sub/real.txt' , readlink( $back . '/link.txt' ) ) ;

        // The target was written as a relative path, so it still points inside the extracted
        // tree, at the file the extraction just wrote.
        $this->assertSame( 'real' , file_get_contents( $back . '/link.txt' ) ) ;
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     * @throws UnsupportedCompressionException
     */
    public function testALinkToAnAbsolutePathSurvivesTheRoundTripOnlyWhenAsked(): void
    {
        $this->skipWhenSymlinksAreUnreliable() ;

        // zip() stores an absolute target as it stands, and unzip() refuses to recreate it: the
        // archive is faithful and, by default, not restorable. This is the whole round trip a
        // backup of a site whose uploads is an absolute link goes through.

        $source   = $this->tempDir . DIRECTORY_SEPARATOR . 'tree' ;
        $uploads  = $this->tempDir . DIRECTORY_SEPARATOR . 'uploads' ;

        makeDirectory( $source ) ;
        makeDirectory( $uploads ) ;
        file_put_contents( $uploads . DIRECTORY_SEPARATOR . 'photo.jpg' , 'photo' ) ;
        symlink( $uploads , $source . DIRECTORY_SEPARATOR . 'uploads' ) ;

        $archive = zip( $source , $this->tempDir . '/absolute.zip' , preserveRoot: $source ) ;

        $this->assertSame( 0o120000 , $this->entryTypeBits( $archive , 'uploads' ) ) ;
        $this->assertSame( $uploads , $this->entryContent( $archive , 'uploads' ) ) ;

        try
        {
            unzip( $archive , $this->tempDir . DIRECTORY_SEPARATOR . 'refused' ) ;
            $this->fail( 'The absolute link should have been refused.' ) ;
        }
        catch ( FileException $exception )
        {
            $this->assertStringContainsString( 'symbolic link to the absolute path' , $exception->getMessage() ) ;
        }

        $back = $this->tempDir . DIRECTORY_SEPARATOR . 'back' ;

        unzip( $archive , $back , [ ZipOption::ALLOW_UNSAFE_LINKS => true ] ) ;

        $this->assertTrue( is_link( $back . '/uploads' ) ) ;
        $this->assertSame( $uploads , readlink( $back . '/uploads' ) ) ;
        $this->assertSame( 'photo' , file_get_contents( $back . '/uploads/photo.jpg' ) ) ;
    }

    /**
     * The content an entry holds.
     */
    private function entryContent( string $archive , string $entry ): string
    {
        $zip = new ZipArchive() ;
        $zip->open( $archive ) ;
        $content = $zip->getFromName( $entry ) ;
        $zip->close() ;

        return (string) $content ;
    }

    /**
     * The names of every entry of an archive, sorted.
     *
     * @return string[]
     */
    private function entryNames( string $archive ): array
    {
        $zip = new ZipArchive() ;
        $zip->open( $archive ) ;

        $names = [] ;

        for ( $i = 0 ; $i < $zip->numFiles ; $i++ )
        {
            $names[] = $zip->getNameIndex( $i ) ;
        }

        $zip->close() ;
        sort( $names ) ;

        return $names ;
    }

    /**
     * The Unix type bits stored in an entry's external attributes: 0o120000 for a symbolic link,
     * 0o100000 for a regular file, 0o40000 for a directory.
     */
    private function entryTypeBits( string $archive , string $entry ): int
    {
        $zip = new ZipArchive() ;
        $zip->open( $archive ) ;

        $opSys = 0 ;
        $attr  = 0 ;
        $zip->getExternalAttributesName( $entry , $opSys , $attr ) ;
        $zip->close() ;

        $this->assertSame( ZipArchive::OPSYS_UNIX , $opSys ) ;

        return ( $attr >> 16 ) & 0o170000 ;
    }

    private function skipWhenSymlinksAreUnreliable(): void
    {
        if ( strtoupper( substr( PHP_OS , 0 , 3 ) ) === 'WIN' )
        {
            $this->markTestSkipped( 'Symbolic link tests are not reliable on Windows.' ) ;
        }
    }
}
