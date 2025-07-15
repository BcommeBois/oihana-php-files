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
}