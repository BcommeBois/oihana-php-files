<?php

namespace tests\oihana\files ;

use PHPUnit\Framework\TestCase;

use oihana\files\exceptions\DirectoryException;
use oihana\files\exceptions\FileException;

use function oihana\files\deleteDirectory;
use function oihana\files\getFileSize;
use function oihana\files\makeDirectory;

class GetFileSizeTest extends TestCase
{
    private string $tmpDir;

    /**
     * @throws DirectoryException
     */
    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/get_file_size_' . uniqid();
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
     */
    public function testReturnsTheByteCount(): void
    {
        $file = $this->tmpDir . '/file.bin';
        file_put_contents( $file , 'hello' ); // 5 bytes

        $this->assertSame( 5 , getFileSize( $file ) );
    }

    /**
     * @throws FileException
     */
    public function testReturnsZeroForAnEmptyFile(): void
    {
        $file = $this->tmpDir . '/empty.bin';
        file_put_contents( $file , '' );

        $this->assertSame( 0 , getFileSize( $file ) );
    }

    public function testThrowsWhenFileIsMissing(): void
    {
        $this->expectException( FileException::class );
        getFileSize( $this->tmpDir . '/missing.bin' );
    }
}
