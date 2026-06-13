<?php

namespace tests\oihana\files ;

use PHPUnit\Framework\TestCase;

use oihana\files\exceptions\DirectoryException;
use oihana\files\exceptions\FileException;

use function oihana\files\deleteDirectory;
use function oihana\files\filesAreEqual;
use function oihana\files\makeDirectory;

class FilesAreEqualTest extends TestCase
{
    private string $tmpDir;

    /**
     * @throws DirectoryException
     */
    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/files_are_equal_' . uniqid();
        makeDirectory( $this->tmpDir );
    }

    /**
     * @throws DirectoryException
     */
    protected function tearDown(): void
    {
        deleteDirectory( $this->tmpDir );
    }

    private function makeFile( string $name , string $content ): string
    {
        $path = $this->tmpDir . '/' . $name;
        file_put_contents( $path , $content );
        return $path;
    }

    /**
     * @throws FileException
     */
    public function testReturnsTrueForIdenticalContents(): void
    {
        $a = $this->makeFile( 'a.bin' , 'same bytes' );
        $b = $this->makeFile( 'b.bin' , 'same bytes' );

        $this->assertTrue( filesAreEqual( $a , $b ) );
    }

    /**
     * @throws FileException
     */
    public function testReturnsTrueForTheSameFile(): void
    {
        $a = $this->makeFile( 'a.bin' , 'whatever' );

        // Same path: short-circuits without reading.
        $this->assertTrue( filesAreEqual( $a , $a ) );
    }

    /**
     * @throws FileException
     */
    public function testReturnsFalseWhenSizesDiffer(): void
    {
        $a = $this->makeFile( 'a.bin' , 'short' );
        $b = $this->makeFile( 'b.bin' , 'a much longer content' );

        $this->assertFalse( filesAreEqual( $a , $b ) );
    }

    /**
     * @throws FileException
     */
    public function testReturnsFalseForSameSizeDifferentContents(): void
    {
        $a = $this->makeFile( 'a.bin' , 'aaaa' );
        $b = $this->makeFile( 'b.bin' , 'bbbb' );

        $this->assertFalse( filesAreEqual( $a , $b ) );
    }

    public function testThrowsWhenAFileIsMissing(): void
    {
        $b = $this->makeFile( 'b.bin' , 'data' );

        $this->expectException( FileException::class );
        filesAreEqual( $this->tmpDir . '/missing.bin' , $b );
    }
}
