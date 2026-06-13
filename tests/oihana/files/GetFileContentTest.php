<?php

namespace tests\oihana\files ;

use RuntimeException;

use PHPUnit\Framework\TestCase;

use oihana\files\exceptions\DirectoryException;
use oihana\files\exceptions\FileException;

use function oihana\files\deleteDirectory;
use function oihana\files\getFileContent;
use function oihana\files\makeDirectory;

class GetFileContentTest extends TestCase
{
    private string $tmpDir;

    /**
     * @throws DirectoryException
     */
    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/get_file_content_' . uniqid();
        makeDirectory( $this->tmpDir );
    }

    /**
     * @throws DirectoryException
     */
    protected function tearDown(): void
    {
        deleteDirectory( $this->tmpDir );
    }

    private function makeFile( string $content ): string
    {
        $path = $this->tmpDir . '/file.txt';
        file_put_contents( $path , $content );
        return $path;
    }

    /**
     * @throws FileException
     */
    public function testReadsTheWholeContent(): void
    {
        $content = "line 1\nline 2\nline 3";
        $file    = $this->makeFile( $content );

        $this->assertSame( $content , getFileContent( $file ) );
    }

    /**
     * @throws FileException
     */
    public function testReturnsAnEmptyStringForAnEmptyFile(): void
    {
        $file = $this->makeFile( '' );

        $this->assertSame( '' , getFileContent( $file ) );
    }

    /**
     * @throws FileException
     */
    public function testReadsUpToTheByteCap(): void
    {
        $content = 'within the cap';
        $file    = $this->makeFile( $content );

        $this->assertSame( $content , getFileContent( $file , 1024 ) );
    }

    public function testThrowsWhenSizeExceedsMaxBytes(): void
    {
        $file = $this->makeFile( 'this content is definitely longer than five bytes' );

        $this->expectException( RuntimeException::class );
        $this->expectExceptionMessage( 'exceeds maximum' );
        getFileContent( $file , 5 );
    }

    public function testThrowsWhenFileIsMissing(): void
    {
        $this->expectException( FileException::class );
        getFileContent( $this->tmpDir . '/missing.txt' );
    }
}
