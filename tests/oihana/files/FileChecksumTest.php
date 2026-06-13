<?php

namespace tests\oihana\files ;

use PHPUnit\Framework\TestCase;

use oihana\files\exceptions\DirectoryException;
use oihana\files\exceptions\FileException;

use function oihana\files\deleteDirectory;
use function oihana\files\fileChecksum;
use function oihana\files\makeDirectory;

class FileChecksumTest extends TestCase
{
    private string $tmpDir;

    /**
     * @throws DirectoryException
     */
    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/file_checksum_' . uniqid();
        makeDirectory( $this->tmpDir );
    }

    /**
     * @throws DirectoryException
     */
    protected function tearDown(): void
    {
        deleteDirectory( $this->tmpDir );
    }

    private function makeFile( string $content = 'payload' ): string
    {
        $path = $this->tmpDir . '/file.bin';
        file_put_contents( $path , $content );
        return $path;
    }

    /**
     * @throws FileException
     */
    public function testComputesSha256ByDefault(): void
    {
        $content = 'the quick brown fox';
        $file    = $this->makeFile( $content );

        $this->assertSame( hash( 'sha256' , $content ) , fileChecksum( $file ) );
    }

    /**
     * @throws FileException
     */
    public function testComputesWithACustomAlgorithm(): void
    {
        $content = 'the quick brown fox';
        $file    = $this->makeFile( $content );

        $this->assertSame( hash( 'md5'  , $content ) , fileChecksum( $file , 'md5'  ) );
        $this->assertSame( hash( 'sha1' , $content ) , fileChecksum( $file , 'sha1' ) );
    }

    public function testThrowsOnUnsupportedAlgorithm(): void
    {
        $file = $this->makeFile();

        $this->expectException( FileException::class );
        $this->expectExceptionMessage( 'Unsupported hash algorithm' );
        fileChecksum( $file , 'not-a-real-algo' );
    }

    public function testThrowsWhenFileIsMissing(): void
    {
        $this->expectException( FileException::class );
        fileChecksum( $this->tmpDir . '/missing.bin' );
    }
}
