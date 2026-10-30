<?php

namespace tests\oihana\files;

use oihana\files\exceptions\DirectoryException;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;
use function oihana\files\deleteDirectory;
use function oihana\files\deleteTemporaryDirectory;
use function oihana\files\getTemporaryDirectory;

class DeleteTemporaryDirectoryTest extends TestCase
{
    private string $baseTemp;
    private string $outside;

    protected function setUp(): void
    {
        $this->baseTemp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'oihana_test_' . uniqid();
        mkdir( $this->baseTemp , 0777, true );

        // A disposable tree that does NOT live in the temporary directory, which is the whole
        // point: sys_get_temp_dir() is not a parent of the working directory.
        $this->outside = dirname( __DIR__ , 3 ) . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'oihana_outside_' . uniqid() ;
        mkdir( $this->outside , 0777 , true ) ;
        file_put_contents( $this->outside . DIRECTORY_SEPARATOR . 'keepme.txt' , 'content' ) ;
    }

    /**
     * @throws DirectoryException
     */
    protected function tearDown(): void
    {
        if ( is_dir( $this->baseTemp ) )
        {
            deleteDirectory( $this->baseTemp , assertable: false );
        }

        if ( is_dir( $this->outside ) )
        {
            deleteDirectory( $this->outside , assertable: false );
        }
    }

    /**
     * @throws DirectoryException
     */
    public function testDeletesEmptyDirectory(): void
    {
        $dir = getTemporaryDirectory([$this->baseTemp, 'empty']);
        mkdir($dir, 0777, true);

        $this->assertDirectoryExists($dir);
        $this->assertTrue(deleteTemporaryDirectory([$this->baseTemp, 'empty']));
        $this->assertDirectoryDoesNotExist($dir);
    }

    public function testReturnsFalseWhenDirectoryResolvesToEmptyString(): void
    {
        // getTemporaryDirectory('/') rtrims to '' on POSIX -> early return false.
        $this->assertFalse(deleteTemporaryDirectory('/'));
    }

    /**
     * @throws DirectoryException
     */
    public function testDeletesDirectoryWithFiles(): void
    {
        $dir = getTemporaryDirectory([$this->baseTemp, 'with_files']);
        mkdir($dir, 0777, true);
        file_put_contents($dir . DIRECTORY_SEPARATOR . 'test.txt', 'hello');

        $this->assertFileExists($dir . '/test.txt');
        $this->assertTrue(deleteTemporaryDirectory([$this->baseTemp, 'with_files']));
        $this->assertDirectoryDoesNotExist($dir);
    }

    /**
     * @throws DirectoryException
     */
    public function testReturnsTrueIfDirectoryDoesNotExist(): void
    {
        $result = deleteTemporaryDirectory([$this->baseTemp, 'nonexistent']);
        $this->assertTrue($result);
    }

    /**
     * @throws DirectoryException
     */
    public function testThrowsExceptionOnNonWritableDirectory(): void
    {
        $dir = getTemporaryDirectory([$this->baseTemp, 'locked']);
        mkdir($dir, 0555, true); // read & execute only

        $this->expectException(DirectoryException::class);
        deleteTemporaryDirectory([$this->baseTemp, 'locked'], assertable: true, isWritable: true);
    }

    /**
     * @throws DirectoryException
     */
    public function testDoesNotDeleteSysTempRoot(): void
    {
        $this->assertFalse(deleteTemporaryDirectory( sys_get_temp_dir() ) );
    }

    /**
     * @throws DirectoryException
     */
    public function testReturnsFalseIfPathIsEmpty(): void
    {
        $this->assertFalse(deleteTemporaryDirectory(null));
        $this->assertFalse(deleteTemporaryDirectory(''));
        $this->assertFalse(deleteTemporaryDirectory([]));
    }

    /**
     * @throws DirectoryException
     */
    public function testThrowsExceptionOnUnreadableDirectory(): void
    {
        $unreadableDir = $this->baseTemp . '/unreadable';

        mkdir($unreadableDir, 0777, true);
        chmod($unreadableDir, 0000);

        $this->expectException( DirectoryException::class );

        try
        {
            deleteTemporaryDirectory([$this->baseTemp, 'unreadable']);
        }
        finally
        {
            chmod( $unreadableDir , 0777 ) ;
        }
    }

    /**
     * @throws DirectoryException
     */
    public function testRefusesAPathClimbingOutOfTheTemporaryDirectory(): void
    {
        // getTemporaryDirectory() joins a relative path without resolving it, so the climb
        // arrived here untouched and the old guard, which only compared against the temporary
        // directory itself, let it through.

        $climb = str_repeat( '..' . DIRECTORY_SEPARATOR , 12 ) . ltrim( $this->outside , DIRECTORY_SEPARATOR ) ;

        $this->assertFalse( deleteTemporaryDirectory( $climb ) ) ;
        $this->assertFileExists( $this->outside . DIRECTORY_SEPARATOR . 'keepme.txt' ) ;
    }

    /**
     * @throws DirectoryException
     */
    public function testRefusesAnAbsolutePathOutsideTheTemporaryDirectory(): void
    {
        $this->assertFalse( deleteTemporaryDirectory( $this->outside ) ) ;
        $this->assertDirectoryExists( $this->outside ) ;
        $this->assertFileExists( $this->outside . DIRECTORY_SEPARATOR . 'keepme.txt' ) ;
    }

    /**
     * @throws DirectoryException
     */
    public function testRefusesAPathReachingOutThroughALinkedAncestor(): void
    {
        if ( strtoupper( substr( PHP_OS , 0 , 3 ) ) === 'WIN' )
        {
            $this->markTestSkipped( 'Symbolic link tests are not reliable on Windows.' ) ;
        }

        // The link sits in the temporary directory, so the path reads as temporary, but what it
        // leads to does not. The parent is resolved for exactly this reason.

        mkdir( $this->outside . DIRECTORY_SEPARATOR . 'inside' ) ;
        file_put_contents( $this->outside . '/inside/keepme.txt' , 'content' ) ;

        $link = $this->baseTemp . DIRECTORY_SEPARATOR . 'gateway' ;
        symlink( $this->outside , $link ) ;

        $this->assertFalse( deleteTemporaryDirectory( $link . DIRECTORY_SEPARATOR . 'inside' ) ) ;
        $this->assertFileExists( $this->outside . '/inside/keepme.txt' ) ;
    }

    /**
     * @throws DirectoryException
     */
    public function testRemovesALinkSittingInTheTemporaryDirectoryAndKeepsItsTarget(): void
    {
        if ( strtoupper( substr( PHP_OS , 0 , 3 ) ) === 'WIN' )
        {
            $this->markTestSkipped( 'Symbolic link tests are not reliable on Windows.' ) ;
        }

        // The last segment is left as it stands, so the link is judged by where it sits. It is
        // removed on its own, and what it points at is left alone.

        $link = $this->baseTemp . DIRECTORY_SEPARATOR . 'pointer' ;
        symlink( $this->outside , $link ) ;

        $this->assertTrue( deleteTemporaryDirectory( $link ) ) ;
        $this->assertFalse( is_link( $link ) ) ;
        $this->assertFileExists( $this->outside . DIRECTORY_SEPARATOR . 'keepme.txt' ) ;
    }
}
