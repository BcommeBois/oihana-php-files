<?php

namespace oihana\files ;

use oihana\files\exceptions\DirectoryException;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;

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
}