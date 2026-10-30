<?php

namespace tests\oihana\files;

use oihana\files\exceptions\DirectoryException;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use Random\RandomException;
use function oihana\files\makePrivateTemporaryDirectory;

#[CoversFunction('oihana\files\makePrivateTemporaryDirectory')]
final class MakePrivateTemporaryDirectoryTest extends TestCase
{
    private string $name = '' ;

    /**
     * @throws RandomException
     */
    protected function setUp() :void
    {
        $this->name = 'oihana-private-' . bin2hex( random_bytes( 6 ) ) ;
    }

    protected function tearDown() :void
    {
        exec( sprintf( 'rm -rf %s' , escapeshellarg( sys_get_temp_dir() . '/' . $this->name ) ) ) ;
    }

    /**
     * @throws DirectoryException
     */
    public function testTheDirectoryIsPrivateToItsOwner() :void
    {
        $path = makePrivateTemporaryDirectory( [ $this->name , 'backup' ] ) ;

        $this->assertDirectoryExists( $path ) ;
        $this->assertSame( 0700 , $this->mode( $path ) ) ;
    }

    /**
     * @throws DirectoryException
     */
    public function testTheDirectoriesItCreatesOnTheWayArePrivateToo() :void
    {
        $path = makePrivateTemporaryDirectory( [ $this->name , 'backup' , 'example.com' , 'www' ] ) ;

        $this->assertSame( 0700 , $this->mode( dirname( $path ) ) ) ;
        $this->assertSame( 0700 , $this->mode( dirname( $path , 2 ) ) ) ;
    }

    /**
     * A directory left behind by an earlier run keeps the permissions it was created with, so the
     * mode is applied again rather than only passed to mkdir().
     *
     * @throws DirectoryException
     */
    public function testADirectoryAlreadyThereIsTightenedUp() :void
    {
        $path = makePrivateTemporaryDirectory( [ $this->name , 'backup' ] ) ;

        chmod( $path , 0755 ) ;

        $again = makePrivateTemporaryDirectory( [ $this->name , 'backup' ] ) ;

        $this->assertSame( $path , $again ) ;
        $this->assertSame( 0700 , $this->mode( $again ) ) ;
    }

    /**
     * @throws DirectoryException
     */
    public function testItStaysUnderTheSystemTemporaryDirectory() :void
    {
        $path = makePrivateTemporaryDirectory( $this->name ) ;

        $this->assertStringStartsWith( realpath( sys_get_temp_dir() ) , realpath( $path ) ) ;
    }

    /**
     * @throws DirectoryException
     */
    public function testEmptySegmentsAreDropped() :void
    {
        $path = makePrivateTemporaryDirectory( [ '' , $this->name , '  ' , 'backup' ] ) ;

        $this->assertSame( realpath( sys_get_temp_dir() . '/' . $this->name . '/backup' ) , realpath( $path ) ) ;
    }

    /**
     * @throws DirectoryException
     */
    public function testItIsCreatedUnderTheBaseGiven() :void
    {
        $base = sys_get_temp_dir() . '/' . $this->name . '/volume' ;

        mkdir( $base , 0755 , true ) ;

        $path = makePrivateTemporaryDirectory( [ 'work' , 'backup' ] , $base ) ;

        $this->assertSame( $base . '/work/backup' , $path ) ;
        $this->assertSame( 0700 , $this->mode( $path ) ) ;
        $this->assertSame( 0700 , $this->mode( $base . '/work' ) ) ;
        $this->assertSame( 0755 , $this->mode( $base ) , 'The base is not this function\'s to change.' ) ;
    }

    /**
     * @return array<string, array{string|list<string>}>
     */
    public static function emptyPaths() :array
    {
        return
        [
            'an empty string'      => [ ''             ] ,
            'a blank string'       => [ '   '          ] ,
            'no segment'           => [ []             ] ,
            'only empty segments'  => [ [ '' , ' ' ]   ] ,
        ] ;
    }

    /**
     * Given no name, the directory would be the base itself — `/tmp` — and whoever deletes it when
     * the work is done would delete the whole of it.
     *
     * @param string|list<string> $path
     */
    #[DataProvider('emptyPaths')]
    public function testAnEmptyPathIsRefused( string|array $path ) :void
    {
        $this->expectException( DirectoryException::class ) ;

        makePrivateTemporaryDirectory( $path ) ;
    }

    /**
     * @param string|list<string> $path
     */
    #[DataProvider('emptyPaths')]
    public function testAnEmptyPathIsRefusedUnderABaseToo( string|array $path ) :void
    {
        $this->expectException( DirectoryException::class ) ;

        makePrivateTemporaryDirectory( $path , sys_get_temp_dir() ) ;
    }

    private function mode( string $path ) :int
    {
        clearstatcache( true , $path ) ;

        return fileperms( $path ) & 0777 ;
    }
}
