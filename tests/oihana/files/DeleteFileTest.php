<?php

namespace oihana\files ;

use oihana\files\exceptions\DirectoryException;
use oihana\files\exceptions\FileException;
use PHPUnit\Framework\TestCase;

class DeleteFileTest extends TestCase
{
    private string $testDirectory ;
    private string $testFile;

    /**
     * @throws DirectoryException
     */
    protected function setUp(): void
    {
        $this->testDirectory = sys_get_temp_dir() . '/oihana/php-files/tests/';
        makeDirectory( $this->testDirectory );
        $this->testFile = $this->testDirectory . 'file.txt';
    }

    /**
     * @throws DirectoryException
     */
    protected function tearDown(): void
    {
        deleteDirectory( $this->testDirectory );
    }

    /**
     * @throws FileException
     */
    public function testDeleteFileSuccessfully(): void
    {
        file_put_contents( $this->testFile , 'test content' ) ;

        $this->assertFileExists($this->testFile);

        $result = deleteFile( $this->testFile );

        $this->assertTrue($result);
        $this->assertFileDoesNotExist( $this->testFile ) ;
    }

    public function testDeleteFileThrowsExceptionWhenFileDoesNotExist(): void
    {
        $this->expectException(FileException::class);
        deleteFile($this->testFile);
    }
}