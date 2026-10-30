<?php

namespace tests\oihana\files;

use oihana\files\exceptions\DirectoryException;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;
use function oihana\files\deleteDirectory;

class DeleteDirectoryTest extends TestCase
{
    private vfsStreamDirectory $root;

    protected function setUp(): void
    {
        $this->root = vfsStream::setup('testDir' ) ; // Setup a virtual filesystem for testing
    }

    /**
     * @throws DirectoryException
     */
    public function testDeleteNonEmptyDirectory():void
    {
        // Create a directory structure
        vfsStream::create
        ([
            'dirToDelete' =>
            [
                'subdir' =>
                [
                    'file1.txt' => 'content',
                    'file2.txt' => 'content'
                ],
                'file3.txt' => 'content'
            ]
        ] , $this->root ) ;

        $directoryPath = vfsStream::url('testDir/dirToDelete');

        // Call the function
        $result = deleteDirectory( $directoryPath );

        // Assertions
        $this->assertTrue($result);
        $this->assertFalse($this->root->hasChild('dirToDelete'));
    }

    /**
     * @return void
     * @throws DirectoryException
     */
    public function testDeleteEmptyDirectory():void
    {
        // Create an empty directory
        vfsStream::create( [ 'emptyDir' => [] ] , $this->root ) ;

        $directoryPath = vfsStream::url('testDir/emptyDir');

        // Call the function
        $result = deleteDirectory($directoryPath);

        // Assertions
        $this->assertTrue($result);
        $this->assertFalse($this->root->hasChild('emptyDir'));
    }

    /**
     * @return void
     * @throws DirectoryException
     */
    public function testNonExistentDirectory():void
    {
        $this->expectException( DirectoryException::class ) ;
        $this->expectExceptionMessageMatches('/The path ".+nonExistentDir" is not a valid directory\./');

        $directoryPath = vfsStream::url('testDir/nonExistentDir' ) ;

        // Call the function
        deleteDirectory( $directoryPath ) ;
    }

    public function testNullDirectoryPath():void
    {
        $this->expectException( DirectoryException::class ) ;
        $this->expectExceptionMessage( 'The directory path must not be empty.' );

        // Call the function with null path
        deleteDirectory(null);
    }

    public function testEmptyDirectoryPath():void
    {
        $this->expectException( DirectoryException::class ) ;
        $this->expectExceptionMessage( 'The directory path must not be empty.' );
        deleteDirectory('' ) ;
    }

    public function testDirectoryNotReadable():void
    {
        $directoryPath = sys_get_temp_dir() . '/unreadableDir_' . uniqid();
        mkdir($directoryPath) ;
        chmod($directoryPath, 0000); // Remove all permissions
        $this->expectException(DirectoryException::class);
        $this->expectExceptionMessageMatches('/is not readable/');
        try
        {
            deleteDirectory( $directoryPath ) ;
        }
        catch ( DirectoryException $e )
        {
            chmod($directoryPath, 0777);
            rmdir($directoryPath);
            throw $e;
        }
    }

    public function testDirectoryNotWritable():void
    {
        if (strtoupper( substr(PHP_OS, 0, 3)) === 'WIN')
        {
            $this->markTestSkipped( 'Permission tests are not reliable on Windows.' ) ;
        }

        // Create a directory with read-only permissions
        $directoryPath = sys_get_temp_dir() . '/readonlyDir_' . uniqid();
        mkdir($directoryPath);
        chmod($directoryPath, 0555); // Read and execute only

        $this->expectException(DirectoryException::class);
        $this->expectExceptionMessageMatches('/is not writable/');

        try
        {
            deleteDirectory( $directoryPath );
        }
        catch ( DirectoryException $e )
        {
            // Clean up
            @chmod( $directoryPath , 0777 ) ;
            @rmdir($directoryPath);
            throw $e;
        }
    }

    public function testPathIsFile():void
    {
        // Create a file instead of a directory
        vfsStream::create( [ 'file.txt' => 'content' ] , $this->root ) ;

        $filePath = vfsStream::url('testDir/file.txt' ) ;

        $this->expectException(DirectoryException::class);
        $this->expectExceptionMessageMatches('/is not a valid directory/');

        // Call the function with a file path
        deleteDirectory( $filePath );
    }

    public function testThrowsWhenAChildFileCannotBeRemoved(): void
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
        {
            $this->markTestSkipped('Permission tests are not reliable on Windows.');
        }

        // Target is writable, but the read-only subdir prevents unlinking its file.
        $base = sys_get_temp_dir() . '/oihana_del_' . uniqid();
        mkdir($base . '/ro', 0777, true);
        file_put_contents($base . '/ro/file.txt', 'x');
        chmod($base . '/ro', 0555);

        try
        {
            $this->expectException(DirectoryException::class);
            $this->expectExceptionMessageMatches('/Failed to remove file/');
            deleteDirectory($base);
        }
        finally
        {
            @chmod($base . '/ro', 0777);
            @unlink($base . '/ro/file.txt');
            @rmdir($base . '/ro');
            @rmdir($base);
        }
    }

    public function testThrowsWhenAChildDirectoryCannotBeRemoved(): void
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
        {
            $this->markTestSkipped('Permission tests are not reliable on Windows.');
        }

        // The read-only middle dir prevents rmdir of its empty leaf subdir.
        $base = sys_get_temp_dir() . '/oihana_del_' . uniqid();
        mkdir($base . '/mid/leaf', 0777, true);
        chmod($base . '/mid', 0555);

        try
        {
            $this->expectException(DirectoryException::class);
            $this->expectExceptionMessageMatches('/Failed to remove directory/');
            deleteDirectory($base);
        }
        finally
        {
            @chmod($base . '/mid', 0777);
            @rmdir($base . '/mid/leaf');
            @rmdir($base . '/mid');
            @rmdir($base);
        }
    }

    public function testWrapsUnexpectedIterationError(): void
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
        {
            $this->markTestSkipped('Permission tests are not reliable on Windows.');
        }

        // An unreadable subdir makes the recursive iterator throw a non-DirectoryException.
        $base = sys_get_temp_dir() . '/oihana_del_' . uniqid();
        mkdir($base . '/unreadable', 0777, true);
        chmod($base . '/unreadable', 0000);

        try
        {
            $this->expectException(DirectoryException::class);
            $this->expectExceptionMessageMatches('/An error occurred while deleting directory/');
            deleteDirectory($base);
        }
        finally
        {
            @chmod($base . '/unreadable', 0777);
            @rmdir($base . '/unreadable');
            @rmdir($base);
        }
    }

    /**
     * @throws DirectoryException
     */
    public function testPathIsItselfALinkRemovesTheLinkAndKeepsItsTarget(): void
    {
        $this->skipWhenSymlinksAreUnreliable() ;

        $base = $this->makeTemporaryDirectory() ;

        try
        {
            mkdir( $base . '/target' ) ;
            file_put_contents( $base . '/target/keepme.txt' , 'content' ) ;
            symlink( $base . '/target' , $base . '/link' ) ;

            $this->assertTrue( deleteDirectory( $base . '/link' ) ) ;

            $this->assertFalse( is_link( $base . '/link' ) ) ;
            $this->assertDirectoryExists( $base . '/target' ) ;
            $this->assertFileExists( $base . '/target/keepme.txt' ) ;
            $this->assertSame( 'content' , file_get_contents( $base . '/target/keepme.txt' ) ) ;
        }
        finally
        {
            $this->removeTree( $base ) ;
        }
    }

    /**
     * @throws DirectoryException
     */
    public function testRemovesADanglingLink(): void
    {
        $this->skipWhenSymlinksAreUnreliable() ;

        $base = $this->makeTemporaryDirectory() ;

        try
        {
            mkdir( $base . '/tree' ) ;
            symlink( $base . '/never-existed' , $base . '/tree/dangling' ) ;

            $this->assertTrue( is_link( $base . '/tree/dangling' ) ) ;
            $this->assertTrue( deleteDirectory( $base . '/tree' ) ) ;
            $this->assertDirectoryDoesNotExist( $base . '/tree' ) ;
        }
        finally
        {
            $this->removeTree( $base ) ;
        }
    }

    /**
     * @throws DirectoryException
     */
    public function testRemovesALinkToADirectoryAndKeepsItsTarget(): void
    {
        $this->skipWhenSymlinksAreUnreliable() ;

        $base = $this->makeTemporaryDirectory() ;

        try
        {
            mkdir( $base . '/tree' ) ;
            mkdir( $base . '/outside' ) ;
            file_put_contents( $base . '/outside/keepme.txt' , 'content' ) ;
            symlink( $base . '/outside' , $base . '/tree/linked' ) ;

            $this->assertTrue( deleteDirectory( $base . '/tree' ) ) ;

            $this->assertDirectoryDoesNotExist( $base . '/tree' ) ;
            $this->assertDirectoryExists( $base . '/outside' ) ;
            $this->assertFileExists( $base . '/outside/keepme.txt' ) ;
            $this->assertSame( 'content' , file_get_contents( $base . '/outside/keepme.txt' ) ) ;
        }
        finally
        {
            $this->removeTree( $base ) ;
        }
    }

    /**
     * @throws DirectoryException
     */
    public function testRemovesALinkToAFileAndKeepsItsTarget(): void
    {
        $this->skipWhenSymlinksAreUnreliable() ;

        $base = $this->makeTemporaryDirectory() ;

        try
        {
            mkdir( $base . '/tree' ) ;
            file_put_contents( $base . '/keepme.txt' , 'content' ) ;
            symlink( $base . '/keepme.txt' , $base . '/tree/linked.txt' ) ;

            $this->assertTrue( deleteDirectory( $base . '/tree' ) ) ;

            $this->assertDirectoryDoesNotExist( $base . '/tree' ) ;
            $this->assertFileExists( $base . '/keepme.txt' ) ;
            $this->assertSame( 'content' , file_get_contents( $base . '/keepme.txt' ) ) ;
        }
        finally
        {
            $this->removeTree( $base ) ;
        }
    }

    /**
     * @throws DirectoryException
     */
    public function testRemovesLinksPointingInsideTheTree(): void
    {
        $this->skipWhenSymlinksAreUnreliable() ;

        // 'self' points at the root of the tree, which the walk removes only once it is over: the
        // link is therefore still a live link to a directory when its own turn comes, whatever
        // order the filesystem hands the entries back in. That is what makes this case
        // order-independent, where a link to 'm_target' only catches the defect when the
        // filesystem happens to hand the link over before its target.
        //
        // It is also the shape that would make a walk following links turn in circles.

        $base = $this->makeTemporaryDirectory() ;

        try
        {
            mkdir( $base . '/tree/m_target' , 0777 , true ) ;
            file_put_contents( $base . '/tree/m_target/file.txt' , 'content' ) ;
            symlink( $base . '/tree' , $base . '/tree/self' ) ;
            symlink( $base . '/tree/m_target' , $base . '/tree/a_link' ) ;
            symlink( $base . '/tree/m_target' , $base . '/tree/z_link' ) ;

            $this->assertTrue( deleteDirectory( $base . '/tree' ) ) ;
            $this->assertDirectoryDoesNotExist( $base . '/tree' ) ;
        }
        finally
        {
            $this->removeTree( $base ) ;
        }
    }

    public function testThrowsNamingTheLinkAndNotItsTargetWhenALinkCannotBeRemoved(): void
    {
        $this->skipWhenSymlinksAreUnreliable() ;

        // The read-only parent prevents unlinking the link it holds.

        $base = $this->makeTemporaryDirectory() ;
        $link = $base . '/ro/linked' ;

        try
        {
            mkdir( $base . '/ro' ) ;
            mkdir( $base . '/outside' ) ;
            symlink( $base . '/outside' , $link ) ;
            chmod( $base . '/ro' , 0555 ) ;

            $this->expectException( DirectoryException::class ) ;
            $this->expectExceptionMessage( sprintf( 'Failed to remove symbolic link "%s".' , $link ) ) ;

            deleteDirectory( $base ) ;
        }
        finally
        {
            @chmod( $base . '/ro' , 0777 ) ;
            $this->removeTree( $base ) ;
        }
    }

    public function testThrowsWhenPathIsALinkThatCannotBeRemoved(): void
    {
        $this->skipWhenSymlinksAreUnreliable() ;

        // The link is removed on its own, so what stops it is its parent: read-only, nothing can
        // be unlinked from it. The target stays reachable, so the assertions on $path still pass.

        $base = $this->makeTemporaryDirectory() ;
        $link = $base . '/ro/pointer' ;

        try
        {
            mkdir( $base . '/ro' ) ;
            mkdir( $base . '/target' ) ;
            symlink( $base . '/target' , $link ) ;
            chmod( $base . '/ro' , 0555 ) ;

            $this->expectException( DirectoryException::class ) ;
            $this->expectExceptionMessage( sprintf( 'Failed to remove symbolic link "%s".' , $link ) ) ;

            deleteDirectory( $link ) ;
        }
        finally
        {
            @chmod( $base . '/ro' , 0777 ) ;
            $this->removeTree( $base ) ;
        }
    }

    /**
     * Creates an empty directory of its own in the system temporary directory.
     */
    private function makeTemporaryDirectory(): string
    {
        $path = sys_get_temp_dir() . '/oihana_del_link_' . uniqid() ;
        mkdir( $path , 0777 , true ) ;
        return $path ;
    }

    /**
     * Removes a tree without going through the function under test: a link is unlinked, never
     * followed, so a target living outside the tree is left alone.
     */
    private function removeTree( string $path ): void
    {
        if ( is_link( $path ) || is_file( $path ) )
        {
            @unlink( $path ) ;
            return ;
        }

        if ( !is_dir( $path ) )
        {
            return ;
        }

        foreach ( scandir( $path ) ?: [] as $entry )
        {
            if ( $entry !== '.' && $entry !== '..' )
            {
                $this->removeTree( $path . DIRECTORY_SEPARATOR . $entry ) ;
            }
        }

        @rmdir( $path ) ;
    }

    private function skipWhenSymlinksAreUnreliable(): void
    {
        if ( strtoupper( substr( PHP_OS , 0 , 3 ) ) === 'WIN' )
        {
            $this->markTestSkipped( 'Symbolic link tests are not reliable on Windows.' ) ;
        }
    }
}
