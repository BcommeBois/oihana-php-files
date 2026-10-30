<?php

namespace tests\oihana\files;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\TestCase;

use Random\RandomException;
use function oihana\files\containsFile;

#[CoversFunction('oihana\files\containsFile')]
final class ContainsFileTest extends TestCase
{
    private string $root = '' ;

    /**
     * @throws RandomException
     */
    protected function setUp() :void
    {
        $this->root = sys_get_temp_dir() . '/oihana-contains-file-' . bin2hex( random_bytes( 6 ) ) ;

        mkdir( $this->root , 0700 , true ) ;
    }

    protected function tearDown() :void
    {
        exec( sprintf( 'chmod -R u+rwx %1$s 2>/dev/null; rm -rf %1$s' , escapeshellarg( $this->root ) ) ) ;
    }

    public function testAnEmptyDirectoryHoldsNoFile() :void
    {
        $this->assertFalse( containsFile( $this->root ) ) ;
    }

    public function testEmptyDirectoriesHoldNoFile() :void
    {
        mkdir( $this->root . '/a/b/c' , 0700 , true ) ;
        mkdir( $this->root . '/d' , 0700 , true ) ;

        $this->assertFalse( containsFile( $this->root ) ) ;
    }

    public function testAFileDeepDownIsFound() :void
    {
        mkdir( $this->root . '/a/b/c' , 0700 , true ) ;
        file_put_contents( $this->root . '/a/b/c/dump.sql' , 'x' ) ;

        $this->assertTrue( containsFile( $this->root ) ) ;
    }

    public function testAFileAtTheFirstLevelIsFound() :void
    {
        file_put_contents( $this->root . '/a.txt' , 'x' ) ;

        $this->assertTrue( containsFile( $this->root ) ) ;
    }

    /**
     * A link counts, and is not followed: it is something the directory holds, whatever it
     * points at — here, nothing.
     */
    public function testALinkCounts() :void
    {
        symlink( '/nowhere' , $this->root . '/link' ) ;

        $this->assertTrue( containsFile( $this->root ) ) ;
    }

    public function testALinkToADirectoryCounts() :void
    {
        mkdir( $this->root . '/empty' , 0700 ) ;
        symlink( $this->root . '/empty' , $this->root . '/link' ) ;

        $this->assertTrue( containsFile( $this->root ) ) ;
    }

    public function testAMissingDirectoryHoldsNoFile() :void
    {
        $this->assertFalse( containsFile( $this->root . '/nothing-here' ) ) ;
    }

    /**
     * What cannot be read cannot be shown to be empty.
     */
    public function testAnUnreadableSubDirectoryIsTakenToHoldOne() :void
    {
        $this->skipWhenRoot() ;

        mkdir( $this->root . '/locked' , 0700 ) ;
        chmod( $this->root . '/locked' , 0000 ) ;

        $this->assertTrue( containsFile( $this->root ) ) ;
    }

    public function testAnUnreadableDirectoryIsTakenToHoldOne() :void
    {
        $this->skipWhenRoot() ;

        chmod( $this->root , 0000 ) ;

        $this->assertTrue( containsFile( $this->root ) ) ;
    }

    private function skipWhenRoot() :void
    {
        if ( function_exists( 'posix_geteuid' ) && posix_geteuid() === 0 )
        {
            $this->markTestSkipped( 'root reads every directory, whatever its mode.' ) ;
        }
    }
}
