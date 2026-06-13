<?php

namespace tests\oihana\files ;

use PHPUnit\Framework\TestCase;

use oihana\files\exceptions\DirectoryException;
use oihana\files\exceptions\FileException;

use function oihana\files\deleteDirectory;
use function oihana\files\makeDirectory;
use function oihana\files\renameFile;

class RenameFileTest extends TestCase
{
    private string $tmpDir;

    /**
     * @throws DirectoryException
     */
    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/rename_file_' . uniqid();
        makeDirectory( $this->tmpDir );
    }

    /**
     * @throws DirectoryException
     */
    protected function tearDown(): void
    {
        deleteDirectory( $this->tmpDir );
    }

    /**
     * @throws FileException
     * @throws DirectoryException
     */
    public function testRenamesAFile(): void
    {
        $source = $this->tmpDir . '/old-name.txt';
        file_put_contents( $source , 'data' );
        $dest = $this->tmpDir . '/new-name.txt';

        $this->assertTrue( renameFile( $source , $dest ) );
        $this->assertFileExists( $dest );
        $this->assertStringEqualsFile( $dest , 'data' );
        $this->assertFileDoesNotExist( $source );
    }

    public function testThrowsWhenSourceIsMissing(): void
    {
        $this->expectException( FileException::class );
        renameFile( $this->tmpDir . '/missing.txt' , $this->tmpDir . '/new.txt' );
    }
}
