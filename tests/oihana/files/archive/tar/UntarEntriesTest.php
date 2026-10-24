<?php

namespace tests\oihana\files\archive\tar;

use PHPUnit\Framework\TestCase;
use RuntimeException;

use function oihana\files\archive\tar\tarBinary;
use function oihana\files\archive\tar\untarEntries;
use function oihana\files\deleteDirectory;

class UntarEntriesTest extends TestCase
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
        $this->root   = sys_get_temp_dir() . '/oihana-untar-entries-' . bin2hex( random_bytes( 6 ) ) ;

        mkdir( $this->root . '/tree' , 0777 , true ) ;
    }

    protected function tearDown() :void
    {
        putenv( $this->override === false ? 'OIHANA_TAR_BINARY' : 'OIHANA_TAR_BINARY=' . $this->override ) ;
        tarBinary( refresh: true ) ;

        deleteDirectory( $this->root ) ;
    }

    /**
     * Builds the fixture by running the binary itself, so the listing is parsed from the
     * output of the very program that will extract it.
     */
    private function archive( string $name = 'tree.tar' ) :string
    {
        $path = $this->root . '/' . $name ;

        $process = proc_open
        (
            [ $this->binary , '-C' , $this->root , '-cf' , $path , 'tree' ] ,
            [ 1 => [ 'pipe' , 'w' ] , 2 => [ 'pipe' , 'w' ] ] ,
            $pipes
        ) ;

        fclose( $pipes[ 1 ] ) ;
        fclose( $pipes[ 2 ] ) ;

        $this->assertSame( 0 , proc_close( $process ) , 'The fixture archive must be created' ) ;

        return $path ;
    }

    private function names( array $entries ) :array
    {
        return array_map( static fn( array $e ) => $e[ 'name' ] , $entries ) ;
    }

    public function testFilesAndDirectoriesAreReported(): void
    {
        file_put_contents( $this->root . '/tree/index.php' , str_repeat( 'a' , 120 ) ) ;

        $entries = untarEntries( $this->binary , $this->archive() ) ;

        $this->assertContains( 'tree/' , $this->names( $entries ) ) ;
        $this->assertContains( 'tree/index.php' , $this->names( $entries ) ) ;

        foreach ( $entries as $entry )
        {
            if ( $entry[ 'name' ] === 'tree/' )
            {
                $this->assertTrue( $entry[ 'directory' ] ) ;
            }

            if ( $entry[ 'name' ] === 'tree/index.php' )
            {
                $this->assertFalse( $entry[ 'directory' ] ) ;
                $this->assertSame( 120 , $entry[ 'size' ] ) ;
            }
        }
    }

    /**
     * The reason the listing asks for `--numeric-owner`: it makes the owner column a
     * token with no space in it, which is what lets the name be read as everything after
     * the time. A WordPress upload directory is full of names like this one.
     */
    public function testANameWithSpacesSurvivesTheListing(): void
    {
        file_put_contents( $this->root . '/tree/mon document final.txt' , 'x' ) ;

        $this->assertContains( 'tree/mon document final.txt' , $this->names( untarEntries( $this->binary , $this->archive() ) ) ) ;
    }

    /**
     * And the reason it asks for `--quoting-style=literal`: by default `tar` escapes
     * unusual characters, so the name would come back spelled differently from the name
     * on disk.
     */
    public function testANameWithAQuoteSurvivesTheListing(): void
    {
        file_put_contents( $this->root . "/tree/l'été.txt" , 'x' ) ;

        $this->assertContains( "tree/l'été.txt" , $this->names( untarEntries( $this->binary , $this->archive() ) ) ) ;
    }

    public function testALinkIsReportedUnderItsOwnNameWithoutItsTarget(): void
    {
        file_put_contents( $this->root . '/tree/target.txt' , 'x' ) ;
        symlink( 'target.txt' , $this->root . '/tree/link.txt' ) ;

        $names = $this->names( untarEntries( $this->binary , $this->archive() ) ) ;

        $this->assertContains( 'tree/link.txt' , $names , "The ' -> target' part must not become part of the name" ) ;
    }

    public function testAnArchiveThatCannotBeListedRaises(): void
    {
        $notAnArchive = $this->root . '/notes.txt' ;
        file_put_contents( $notAnArchive , 'this is not a tar archive' ) ;

        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessage( 'could not list' ) ;

        untarEntries( $this->binary , $notAnArchive ) ;
    }

    /**
     * The decision this pins: a line that cannot be read **raises**. Skipping it would
     * leave a `maxExtractedSize` computed from an incomplete listing — a cap that
     * under-counts, which is a guard that no longer guards.
     *
     * Staged with a stand-in for `tar` that prints something unparsable and exits 0,
     * which is the only way to reach the parser with a line GNU tar would never produce.
     */
    public function testALineThatCannotBeParsedRaisesInsteadOfBeingSkipped(): void
    {
        $standIn = $this->root . '/fake-tar' ;

        file_put_contents( $standIn , "#!/bin/sh\necho 'this is not a tar listing line'\n" ) ;
        chmod( $standIn , 0755 ) ;

        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessage( 'could not be read, so the contents cannot be accounted for' ) ;

        untarEntries( $standIn , $this->archive() ) ;
    }
}
