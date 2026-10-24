<?php

namespace tests\oihana\files\archive\tar;

use oihana\files\enums\CompressionType;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function oihana\files\archive\tar\tar;
use function oihana\files\archive\tar\tarBinary;
use function oihana\files\archive\tar\untar;
use function oihana\files\deleteDirectory;

/**
 * Two engines, one extraction.
 *
 * The counterpart of {@see TarEngineTest}, and it came from a defect rather than from a
 * measurement. Since 1.3.0 `tar()` writes through GNU tar, while `untar()` decompressed
 * through PharData — and PharData's writer refuses any path component over the 100-byte
 * `ustar` limit. Measured on 2026-10-24: an archive written by `tar()` holding one such
 * name **could not be read back by `untar()`**. The same archive uncompressed extracted
 * fine, which is what makes the failure so hard to read: it is the rebuild that
 * decompressing performs, not the archive, that is refused. A stock WordPress carries
 * such a path.
 *
 * So these tests are about the two engines agreeing — the same tree out, the same
 * guarantees honoured, and the archives of either engine readable by the other.
 */
class UntarEngineTest extends TestCase
{
    private string|false $override ;
    private string $root ;

    protected function setUp() :void
    {
        $this->override = getenv( 'OIHANA_TAR_BINARY' ) ;

        putenv( 'OIHANA_TAR_BINARY' ) ;

        if ( tarBinary( refresh: true ) === null )
        {
            $this->markTestSkipped( 'No GNU tar on this host: there is only one engine to compare.' ) ;
        }

        $this->root = sys_get_temp_dir() . '/oihana-untar-engine-' . bin2hex( random_bytes( 6 ) ) ;

        mkdir( $this->root . '/tree' , 0777 , true ) ;
    }

    protected function tearDown() :void
    {
        putenv( $this->override === false ? 'OIHANA_TAR_BINARY' : 'OIHANA_TAR_BINARY=' . $this->override ) ;
        tarBinary( refresh: true ) ;

        deleteDirectory( $this->root ) ;
    }

    private function forcePharData() :void
    {
        putenv( 'OIHANA_TAR_BINARY=' ) ;
        tarBinary( refresh: true ) ;
    }

    private function useBinary() :void
    {
        putenv( 'OIHANA_TAR_BINARY' ) ;
        tarBinary( refresh: true ) ;
    }

    /**
     * Writes a tree and archives it with whichever engine `tar()` picks — here the
     * binary, which is the point: these are the archives the library now produces.
     */
    private function archive( array $files , string $name = 'tree.tar.gz' ) :string
    {
        foreach ( $files as $relative => $contents )
        {
            $path = $this->root . '/tree/' . $relative ;

            if ( !is_dir( dirname( $path ) ) )
            {
                mkdir( dirname( $path ) , 0777 , true ) ;
            }

            file_put_contents( $path , $contents ) ;
        }

        return tar( [ $this->root . '/tree' ] , $this->root . '/' . $name , CompressionType::GZIP , $this->root ) ;
    }

    private function extractedFiles( string $directory ) :array
    {
        $found = [] ;

        foreach ( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $directory , \FilesystemIterator::SKIP_DOTS ) ) as $file )
        {
            if ( $file->isFile() )
            {
                $found[] = str_replace( $directory . '/' , '' , $file->getPathname() ) ;
            }
        }

        sort( $found ) ;

        return $found ;
    }

    // -----------------------------------------------------------------
    // The defect
    // -----------------------------------------------------------------

    /**
     * The one that failed before this lot: write with the library, read back with the
     * library.
     */
    public function testAnArchiveThisLibraryWroteCanBeReadBackWhenANameIsLong(): void
    {
        $long = str_repeat( 'e' , 150 ) . '.txt' ;

        $archive = $this->archive( [ $long => 'contents' , 'index.php' => 'short' ] ) ;

        $this->useBinary() ;

        $this->assertTrue( untar( $archive , $this->root . '/out' ) ) ;
        $this->assertSame( 'contents' , file_get_contents( $this->root . '/out/tree/' . $long ) ) ;
    }

    /**
     * And what the operator is told on a host that has no GNU tar: the limit, not a
     * corrupt archive. The previous message — *"filename ... is too long for tar file
     * format"* — read as damage and sent people looking for another backup.
     */
    public function testPharDataNamesTheLimitItCannotPass(): void
    {
        $archive = $this->archive( [ str_repeat( 'e' , 150 ) . '.txt' => 'contents' ] ) ;

        $this->forcePharData() ;

        try
        {
            untar( $archive , $this->root . '/out' ) ;
            $this->fail( 'PharData cannot rebuild an archive holding a name that long.' ) ;
        }
        catch ( RuntimeException $e )
        {
            $message = $e->getMessage() ;

            $this->assertStringContainsString( '100-byte ustar limit' , $message , 'The refusal must name the limit' ) ;
            $this->assertStringContainsString( 'not a damaged archive' , $message , 'and say what it is not' ) ;
            $this->assertStringContainsString( 'OIHANA_TAR_BINARY' , $message , 'and what to do about it' ) ;
        }
    }

    // -----------------------------------------------------------------
    // The two engines agree
    // -----------------------------------------------------------------

    public function testBothEnginesProduceTheSameTree(): void
    {
        $archive = $this->archive
        ([
            'index.php'            => 'index' ,
            'wp-content/plugin.php' => 'plugin' ,
            'uploads/mon document.txt' => 'document' ,
        ]) ;

        $this->useBinary() ;
        untar( $archive , $this->root . '/with-binary' ) ;

        $this->forcePharData() ;
        untar( $archive , $this->root . '/with-phardata' ) ;

        $this->assertSame
        (
            $this->extractedFiles( $this->root . '/with-binary' ) ,
            $this->extractedFiles( $this->root . '/with-phardata' ) ,
            'The same archive must give the same tree whichever engine extracted it'
        ) ;

        $this->assertSame
        (
            'document' ,
            file_get_contents( $this->root . '/with-binary/tree/uploads/mon document.txt' ) ,
            'A name with a space must survive the binary engine'
        ) ;
    }

    public function testBothEnginesReturnTheSameDryRunList(): void
    {
        $archive = $this->archive( [ 'index.php' => 'index' , 'wp-content/plugin.php' => 'plugin' ] ) ;

        $this->useBinary() ;
        $fromBinary = untar( $archive , $this->root . '/out' , [ 'dryRun' => true ] ) ;

        $this->forcePharData() ;
        $fromPharData = untar( $archive , $this->root . '/out' , [ 'dryRun' => true ] ) ;

        sort( $fromBinary ) ;
        sort( $fromPharData ) ;

        $this->assertSame( $fromPharData , $fromBinary ) ;
        $this->assertSame( [ 'tree/index.php' , 'tree/wp-content/plugin.php' ] , $fromBinary ) ;
        $this->assertDirectoryDoesNotExist( $this->root . '/out/tree' , 'A dry run extracts nothing' ) ;
    }

    public function testBothEnginesEnforceTheSizeCap(): void
    {
        $archive = $this->archive( [ 'index.php' => str_repeat( 'a' , 4096 ) ] ) ;

        foreach ( [ 'binary' , 'phardata' ] as $engine )
        {
            $engine === 'binary' ? $this->useBinary() : $this->forcePharData() ;

            $thrown = false ;

            try
            {
                untar( $archive , $this->root . '/out-' . $engine , [ 'maxExtractedSize' => 100 ] ) ;
            }
            catch ( RuntimeException $e )
            {
                $thrown = true ;
                $this->assertStringContainsString( 'decompression bomb' , $e->getMessage() ) ;
            }

            $this->assertTrue( $thrown , sprintf( 'The %s engine must enforce the cap.' , $engine ) ) ;
            $this->assertFileDoesNotExist( $this->root . '/out-' . $engine . '/tree/index.php' , 'and before writing anything' ) ;
        }
    }

    /**
     * Each engine restores the recorded permissions its own way — `tar -p` on one side,
     * a pass over the extracted tree on the other — so both have to be asked, or the
     * one nobody calls stops being tested the day the other becomes the default. That
     * is what happened to `preservePharFilePermissions()` when this lot landed.
     */
    public function testBothEnginesRestorePermissionsOnlyWhenAsked(): void
    {
        $this->archive( [ 'script.sh' => '#!/bin/sh' ] ) ;

        chmod( $this->root . '/tree/script.sh' , 0755 ) ;

        $archive = tar( [ $this->root . '/tree' ] , $this->root . '/perms.tar.gz' , CompressionType::GZIP , $this->root ) ;

        foreach ( [ 'binary' , 'phardata' ] as $engine )
        {
            $engine === 'binary' ? $this->useBinary() : $this->forcePharData() ;

            $kept = $this->root . '/kept-' . $engine ;
            untar( $archive , $kept , [ 'keepPermissions' => true ] ) ;

            $this->assertSame
            (
                '0755' ,
                substr( sprintf( '%o' , fileperms( $kept . '/tree/script.sh' ) ) , -4 ) ,
                sprintf( 'The %s engine must restore the recorded mode when asked.' , $engine )
            ) ;
        }
    }

    /**
     * It used to fail on the second pass, on the PharData path: `decompress()` writes a
     * `.tar` named after the archive and registers it in a list that removing the file
     * does not clear. A caller restoring several sites in one run met it on the second
     * site — which is the shape of the only caller this library has.
     */
    public function testTheSameArchiveCanBeExtractedTwiceInOneProcess(): void
    {
        $archive = $this->archive( [ 'index.php' => 'index' ] ) ;

        foreach ( [ 'binary' , 'phardata' ] as $engine )
        {
            $engine === 'binary' ? $this->useBinary() : $this->forcePharData() ;

            foreach ( [ 'first' , 'second' ] as $pass )
            {
                $out = sprintf( '%s/%s-%s' , $this->root , $engine , $pass ) ;

                $this->assertTrue
                (
                    untar( $archive , $out ) ,
                    sprintf( 'The %s pass must succeed on the %s engine.' , $pass , $engine )
                ) ;

                $this->assertSame( 'index' , file_get_contents( $out . '/tree/index.php' ) ) ;
            }
        }
    }

    /**
     * And nothing of the decompression is left behind — neither the `.tar` nor the alias
     * that gives it a unique name.
     */
    public function testNothingIsLeftBesideTheArchive(): void
    {
        $archive = $this->archive( [ 'index.php' => 'index' ] ) ;

        $this->forcePharData() ;

        untar( $archive , $this->root . '/out' ) ;

        $leftovers = array_values( array_filter
        (
            glob( $this->root . '/*' ) ?: [] ,
            static fn( string $path ) => is_file( $path ) && $path !== $archive
        ) ) ;

        $this->assertSame( [] , $leftovers , 'The .tar and the alias must both be gone' ) ;
    }

    public function testBothEnginesRefuseToOverwriteBeforeWritingAnything(): void
    {
        $archive = $this->archive( [ 'index.php' => 'new' , 'other.php' => 'new' ] ) ;

        foreach ( [ 'binary' , 'phardata' ] as $engine )
        {
            $out = $this->root . '/out-' . $engine ;

            mkdir( $out . '/tree' , 0777 , true ) ;
            file_put_contents( $out . '/tree/index.php' , 'old' ) ;

            $engine === 'binary' ? $this->useBinary() : $this->forcePharData() ;

            $thrown = false ;

            try
            {
                untar( $archive , $out , [ 'overwrite' => false ] ) ;
            }
            catch ( RuntimeException $e )
            {
                $thrown = true ;
                $this->assertStringContainsString( 'already exists and overwrite is disabled' , $e->getMessage() ) ;
            }

            $this->assertTrue( $thrown , sprintf( 'The %s engine must refuse to overwrite.' , $engine ) ) ;
            $this->assertSame( 'old' , file_get_contents( $out . '/tree/index.php' ) , 'and leave the existing file alone' ) ;
            $this->assertFileDoesNotExist( $out . '/tree/other.php' , 'and write none of the others' ) ;
        }
    }
}
