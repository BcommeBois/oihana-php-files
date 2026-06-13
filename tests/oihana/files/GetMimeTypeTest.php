<?php

namespace oihana\files ;

use PHPUnit\Framework\TestCase;

use oihana\files\exceptions\DirectoryException;

class GetMimeTypeTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/get_mime_type_' . uniqid();
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

    public function testDetectsAPlainTextMimeType(): void
    {
        $file = $this->createTempFile( 'notes.txt' , 'just some text' );
        $this->assertSame( 'text/plain' , getMimeType( $file ) );
    }

    public function testReturnsNullForANonExistentPath(): void
    {
        $this->assertNull( getMimeType( $this->tmpDir . '/missing.bin' ) );
    }

    public function testReturnsNullForADirectory(): void
    {
        $this->assertNull( getMimeType( $this->tmpDir ) );
    }

    public function testReturnsNullForAnEmptyPath(): void
    {
        $this->assertNull( getMimeType( '' ) );
    }
}
