<?php

namespace tests\oihana\files\archive\tar;

use PHPUnit\Framework\TestCase;
use RuntimeException;

use function oihana\files\archive\tar\tarBinary;
use function oihana\files\archive\tar\untarWithBinary;
use function oihana\files\deleteDirectory;

class UntarWithBinaryTest extends TestCase
{
    private string $root ;
    private string $binary ;
    private string|false $override ;

    protected function setUp() :void
    {
        // The suite may be running with OIHANA_TAR_BINARY declared empty, which forces
        // PharData everywhere. These tests are about the binary engine, so they look for
        // it themselves rather than skipping — a skip is a failure in this project.

        $this->override = getenv( 'OIHANA_TAR_BINARY' ) ;

        putenv( 'OIHANA_TAR_BINARY' ) ;

        $binary = tarBinary( refresh: true ) ;

        if ( $binary === null )
        {
            $this->markTestSkipped( 'No GNU tar on this host.' ) ;
        }

        $this->binary = $binary ;
        $this->root   = sys_get_temp_dir() . '/oihana-untar-binary-' . bin2hex( random_bytes( 6 ) ) ;

        mkdir( $this->root . '/tree' , 0777 , true ) ;
        mkdir( $this->root . '/out'  , 0777 , true ) ;
    }

    protected function tearDown() :void
    {
        putenv( $this->override === false ? 'OIHANA_TAR_BINARY' : 'OIHANA_TAR_BINARY=' . $this->override ) ;
        tarBinary( refresh: true ) ;

        deleteDirectory( $this->root ) ;
    }

    private function runProcess( array $arguments ) :int
    {
        $process = proc_open( $arguments , [ 1 => [ 'pipe' , 'w' ] , 2 => [ 'pipe' , 'w' ] ] , $pipes ) ;
        fclose( $pipes[ 1 ] ) ;
        fclose( $pipes[ 2 ] ) ;
        return proc_close( $process ) ;
    }

    private function archive( string $name = 'tree.tar' , bool $compressed = false ) :string
    {
        $path = $this->root . '/' . $name ;

        $arguments = [ $this->binary , '-C' , $this->root , $compressed ? '-czf' : '-cf' , $path , 'tree' ] ;

        $this->assertSame( 0 , $this->runProcess( $arguments ) ) ;

        return $path ;
    }

    public function testAnArchiveIsExtracted(): void
    {
        file_put_contents( $this->root . '/tree/index.php' , 'contents' ) ;

        untarWithBinary( $this->binary , $this->archive() , $this->root . '/out' ) ;

        $this->assertSame( 'contents' , file_get_contents( $this->root . '/out/tree/index.php' ) ) ;
    }

    public function testACompressedArchiveIsExtracted(): void
    {
        file_put_contents( $this->root . '/tree/index.php' , 'contents' ) ;

        untarWithBinary( $this->binary , $this->archive( 'tree.tar.gz' , true ) , $this->root . '/out' ) ;

        $this->assertSame( 'contents' , file_get_contents( $this->root . '/out/tree/index.php' ) ) ;
    }

    /**
     * The failure this engine exists for. PharData refuses a path component over the
     * 100-byte `ustar` limit **while writing**, and decompressing with PharData means
     * writing — so a compressed archive holding one such name could not be extracted at
     * all. GNU tar knows no such limit.
     */
    public function testALongNameIsExtracted(): void
    {
        $long = str_repeat( 'e' , 150 ) . '.txt' ;

        file_put_contents( $this->root . '/tree/' . $long , 'contents' ) ;

        untarWithBinary( $this->binary , $this->archive( 'tree.tar.gz' , true ) , $this->root . '/out' ) ;

        $this->assertFileExists( $this->root . '/out/tree/' . $long ) ;
        $this->assertSame( 154 , strlen( $long ) ) ;
    }

    public function testPermissionsAreRestoredOnlyWhenAsked(): void
    {
        $file = $this->root . '/tree/script.sh' ;

        file_put_contents( $file , '#!/bin/sh' ) ;
        chmod( $file , 0755 ) ;

        $archive = $this->archive() ;

        untarWithBinary( $this->binary , $archive , $this->root . '/out' , true ) ;

        $this->assertSame( '0755' , substr( sprintf( '%o' , fileperms( $this->root . '/out/tree/script.sh' ) ) , -4 ) ) ;
    }

    /**
     * Builds a tar by hand, because GNU tar strips `../` from member names as it writes:
     * an archive hostile enough to test with cannot be produced by the program being
     * tested. 512-byte `ustar` header, checksum computed over the header with the
     * checksum field blank.
     */
    private function hostileArchive( string $memberName ) :string
    {
        $contents = "pwned\n" ;

        $header = str_pad( $memberName , 100 , "\0" )
                . str_pad( '0000644' , 8 , "\0" )
                . str_pad( '0000000' , 8 , "\0" )
                . str_pad( '0000000' , 8 , "\0" )
                . sprintf( '%011o' , strlen( $contents ) ) . "\0"
                . sprintf( '%011o' , time() ) . "\0"
                . '        '
                . '0'
                . str_repeat( "\0" , 100 )
                . "ustar\0" . '00'
                . str_repeat( "\0" , 32 )
                . str_repeat( "\0" , 32 )
                . str_repeat( "\0" , 8 )
                . str_repeat( "\0" , 8 )
                . str_repeat( "\0" , 155 )
                . str_repeat( "\0" , 12 ) ;

        $this->assertSame( 512 , strlen( $header ) , 'A ustar header is 512 bytes' ) ;

        $sum = 0 ;

        for ( $i = 0 ; $i < 512 ; $i++ )
        {
            $sum += ord( $header[ $i ] ) ;
        }

        $header = substr_replace( $header , sprintf( '%06o' , $sum ) . "\0 " , 148 , 8 ) ;

        $padding = ( 512 - strlen( $contents ) % 512 ) % 512 ;
        $path    = $this->root . '/hostile.tar' ;

        file_put_contents( $path , $header . $contents . str_repeat( "\0" , $padding ) . str_repeat( "\0" , 1024 ) ) ;

        return $path ;
    }

    /**
     * No flag asks for this: GNU tar strips a leading `/` and refuses any member whose
     * name contains `..`, extracting nothing and exiting non-zero. What this pins is
     * that the non-zero status is reported rather than swallowed — the default path of
     * `untar()`, with no options, has no listing pass and relies on exactly this.
     */
    public function testAMemberEscapingTheOutputDirectoryIsRefused(): void
    {
        $archive = $this->hostileArchive( '../../escaped.txt' ) ;

        try
        {
            untarWithBinary( $this->binary , $archive , $this->root . '/out' ) ;
            $this->fail( 'An archive escaping the output directory must be refused.' ) ;
        }
        catch ( RuntimeException $e )
        {
            $this->assertStringContainsString( 'exited with status' , $e->getMessage() ) ;
            $this->assertFileDoesNotExist( dirname( $this->root ) . '/escaped.txt' ) ;
            $this->assertFileDoesNotExist( $this->root . '/escaped.txt' ) ;
        }
    }

    public function testAnArchiveThatCannotBeReadIsReported(): void
    {
        $notAnArchive = $this->root . '/notes.txt' ;
        file_put_contents( $notAnArchive , 'this is not a tar archive' ) ;

        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessage( 'exited with status' ) ;

        untarWithBinary( $this->binary , $notAnArchive , $this->root . '/out' ) ;
    }
}
