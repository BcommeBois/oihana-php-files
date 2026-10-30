<?php

namespace tests\oihana\files;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\TestCase;

use Random\RandomException;
use function oihana\files\treePaths;

#[CoversFunction('oihana\files\treePaths')]
final class TreePathsTest extends TestCase
{
    private string $root = '' ;

    /**
     * @throws RandomException
     */
    protected function setUp() :void
    {
        $this->root = sys_get_temp_dir() . '/oihana-tree-paths-' . bin2hex( random_bytes( 6 ) ) ;

        mkdir( $this->root . '/content/uploads/2024' , 0755 , true ) ;
        file_put_contents( $this->root . '/content/uploads/2024/a.jpg' , 'a' ) ;
        file_put_contents( $this->root . '/content/.hidden' , 'h' ) ;
    }

    protected function tearDown() :void
    {
        exec( sprintf( 'chmod -R u+rwx %1$s 2>/dev/null; rm -rf %1$s' , escapeshellarg( $this->root ) ) ) ;
    }

    public function testThePathsAreRelativeToTheRootGiven() :void
    {
        $paths = treePaths( $this->root . '/content' , $this->root ) ;

        sort( $paths ) ;

        $this->assertSame
        (
            [ 'content/.hidden' , 'content/uploads' , 'content/uploads/2024' , 'content/uploads/2024/a.jpg' ] ,
            $paths ,
            'A dot file is listed: `.htaccess` is the kind of name that matters.'
        ) ;
    }

    public function testWithoutARootThePathsAreRelativeToTheDirectory() :void
    {
        $paths = treePaths( $this->root . '/content/uploads/' ) ;

        sort( $paths ) ;

        $this->assertSame( [ '2024' , '2024/a.jpg' ] , $paths ) ;
    }

    public function testADirectoryComesBeforeItsContent() :void
    {
        $paths = treePaths( $this->root . '/content/uploads' ) ;

        $this->assertLessThan( array_search( '2024/a.jpg' , $paths , true ) , array_search( '2024' , $paths , true ) ) ;
    }

    /**
     * A link to `/` would otherwise list the whole machine, and a caller removing "what the other
     * tree does not have" would be handed paths outside the directory.
     */
    public function testALinkIsListedAndNotFollowed() :void
    {
        symlink( '/' , $this->root . '/content/uploads/everything' ) ;

        $paths = treePaths( $this->root . '/content' , $this->root ) ;

        $this->assertContains( 'content/uploads/everything' , $paths ) ;
        $this->assertCount( 5 , $paths , 'The link was followed.' ) ;
    }

    public function testADirectoryThatIsALinkHasNoPath() :void
    {
        symlink( $this->root . '/content' , $this->root . '/alias' ) ;

        $this->assertSame( [] , treePaths( $this->root . '/alias' ) ) ;
    }

    public function testAMissingDirectoryHasNoPath() :void
    {
        $this->assertSame( [] , treePaths( $this->root . '/nothing-here' ) ) ;
    }

    public function testAFileIsNoDirectory() :void
    {
        $this->assertSame( [] , treePaths( $this->root . '/content/.hidden' ) ) ;
    }

    public function testAnUnreadableSubDirectoryIsListedWithoutItsContent() :void
    {
        $this->skipWhenRoot() ;

        chmod( $this->root . '/content/uploads/2024' , 0000 ) ;

        $paths = treePaths( $this->root . '/content' ) ;

        sort( $paths ) ;

        $this->assertSame( [ '.hidden' , 'uploads' , 'uploads/2024' ] , $paths ) ;
    }

    /**
     * The walk goes on past an unreadable directory. Stopping there would lose whatever comes
     * after it in the order of the filesystem — so it sits among readable siblings, which have to
     * be there whatever that order is.
     */
    public function testTheWalkGoesOnPastAnUnreadableDirectory() :void
    {
        $this->skipWhenRoot() ;

        $expected = [] ;

        foreach ( [ 'a' , 'c' , 'e' , 'g' , 'i' , 'k' , 'm' , 'o' ] as $name )
        {
            mkdir( $this->root . '/tree/' . $name , 0755 , true ) ;
            file_put_contents( $this->root . '/tree/' . $name . '/file' , 'x' ) ;

            $expected[] = $name ;
            $expected[] = $name . '/file' ;
        }

        mkdir( $this->root . '/tree/f' , 0755 ) ;
        chmod( $this->root . '/tree/f' , 0000 ) ;

        $expected[] = 'f' ;

        $paths = treePaths( $this->root . '/tree' ) ;

        sort( $paths ) ;
        sort( $expected ) ;

        $this->assertSame( $expected , $paths ) ;
    }

    public function testAnUnreadableDirectoryHasNoPath() :void
    {
        $this->skipWhenRoot() ;

        chmod( $this->root . '/content' , 0000 ) ;

        $this->assertSame( [] , treePaths( $this->root . '/content' ) ) ;
    }

    private function skipWhenRoot() :void
    {
        if ( function_exists( 'posix_geteuid' ) && posix_geteuid() === 0 )
        {
            $this->markTestSkipped( 'root reads every directory, whatever its mode.' ) ;
        }
    }
}
