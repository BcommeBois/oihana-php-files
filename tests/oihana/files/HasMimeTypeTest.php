<?php

namespace oihana\files ;

use PHPUnit\Framework\TestCase;

use oihana\files\exceptions\DirectoryException;

class HasMimeTypeTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/has_mime_type_' . uniqid();
        mkdir( $this->tmpDir );
    }

    /**
     * @throws DirectoryException
     */
    protected function tearDown(): void
    {
        deleteDirectory( $this->tmpDir );
    }

    private function createTempFile( string $filename , string $content ): string
    {
        $filePath = $this->tmpDir . '/' . $filename;
        file_put_contents( $filePath , $content );
        return $filePath;
    }

    public function testReturnsTrueWhenMimeTypeMatches(): void
    {
        $file = $this->createTempFile( 'plain.txt' , 'just some text' );
        $this->assertTrue( hasMimeType( $file , [ 'text/plain' ] ) );
    }

    public function testAcceptsASingleStringMimeType(): void
    {
        $file = $this->createTempFile( 'plain.txt' , 'just some text' );
        $this->assertTrue( hasMimeType( $file , 'text/plain' ) );
        $this->assertFalse( hasMimeType( $file , 'application/zip' ) );
    }

    public function testMatchesUsingASubstring(): void
    {
        $file = $this->createTempFile( 'plain.txt' , 'just some text' );
        // 'text/' is a fragment of the detected 'text/plain'
        $this->assertTrue( hasMimeType( $file , [ 'application/json' , 'text/' ] ) );
    }

    public function testReturnsFalseWhenMimeTypeDoesNotMatch(): void
    {
        $file = $this->createTempFile( 'plain.txt' , 'just some text' );
        $this->assertFalse( hasMimeType( $file , [ 'application/zip' ] ) );
    }

    public function testReturnsFalseForNonExistentFile(): void
    {
        $this->assertFalse( hasMimeType( $this->tmpDir . '/missing.bin' , [ 'text/plain' ] ) );
    }

    public function testReturnsFalseWithAnEmptyMimeTypeList(): void
    {
        $file = $this->createTempFile( 'plain.txt' , 'text' );
        $this->assertFalse( hasMimeType( $file , [] ) );
    }
}
