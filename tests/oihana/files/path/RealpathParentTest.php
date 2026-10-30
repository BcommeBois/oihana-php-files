<?php

namespace tests\oihana\files\path;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\TestCase;

use oihana\files\exceptions\DirectoryException;

use function oihana\files\deleteDirectory;
use function oihana\files\path\canonicalizePath;
use function oihana\files\path\realpathParent;

#[CoversFunction('oihana\files\path\realpathParent')]
final class RealpathParentTest extends TestCase
{
    private string $root ;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'oihana_realpath_parent_' . uniqid() ;
        mkdir( $this->root , 0777 , true ) ;
    }

    /**
     * @throws DirectoryException
     */
    protected function tearDown(): void
    {
        if ( is_dir( $this->root ) )
        {
            deleteDirectory( $this->root , assertable: false ) ;
        }
    }

    public function testFallsBackToTheLexicalPathWhenTheParentDoesNotExist(): void
    {
        $path = $this->root . '/absent/leaf' ;

        $this->assertSame( canonicalizePath( $path ) , realpathParent( $path ) ) ;
    }

    public function testKeepsALinkAsItselfInsteadOfFollowingIt(): void
    {
        $this->skipWhenSymlinksAreUnreliable() ;

        mkdir( $this->root . '/target' ) ;
        symlink( $this->root . '/target' , $this->root . '/pointer' ) ;

        $this->assertSame
        (
            realpath( $this->root ) . DIRECTORY_SEPARATOR . 'pointer' ,
            realpathParent( $this->root . '/pointer' )
        ) ;
    }

    public function testKeepsOneSeparatorWhenTheParentIsTheFilesystemRoot(): void
    {
        if ( DIRECTORY_SEPARATOR !== '/' )
        {
            $this->markTestSkipped( 'The filesystem root is spelled differently on Windows.' ) ;
        }

        // realpath( dirname( '/tmp' ) ) is '/', which already ends with a separator. Concatenating
        // without trimming it gives '//tmp', and a comparison against '/tmp' then fails — which is
        // how deleteTemporaryDirectory() came to accept the temporary directory itself on a host
        // where sys_get_temp_dir() is '/tmp', and started emptying it.

        $this->assertSame( '/tmp' , realpathParent( '/tmp' ) ) ;
    }

    public function testResolvesTheParentSoALinkedAncestorCannotHideWhereAPathLeads(): void
    {
        $this->skipWhenSymlinksAreUnreliable() ;

        mkdir( $this->root . '/real/inner' , 0777 , true ) ;
        symlink( $this->root . '/real' , $this->root . '/gateway' ) ;

        $this->assertSame
        (
            realpath( $this->root . '/real' ) . DIRECTORY_SEPARATOR . 'inner' ,
            realpathParent( $this->root . '/gateway/inner' )
        ) ;
    }

    private function skipWhenSymlinksAreUnreliable(): void
    {
        if ( strtoupper( substr( PHP_OS , 0 , 3 ) ) === 'WIN' )
        {
            $this->markTestSkipped( 'Symbolic link tests are not reliable on Windows.' ) ;
        }
    }
}
