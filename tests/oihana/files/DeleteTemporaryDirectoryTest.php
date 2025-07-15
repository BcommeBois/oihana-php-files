<?php

namespace oihana\files ;

use oihana\files\exceptions\DirectoryException;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;

class DeleteTemporaryDirectoryTest extends TestCase
{
    private string $baseTemp;

    protected function setUp(): void
    {
        $this->baseTemp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'oihana_test_' . uniqid();
        mkdir( $this->baseTemp , 0777, true );
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
}