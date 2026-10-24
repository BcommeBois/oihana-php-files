<?php

namespace tests\oihana\files\archive\tar;

use oihana\files\enums\CompressionType;
use PHPUnit\Framework\TestCase;

use Random\RandomException;
use function oihana\files\archive\tar\tarCompressionOfFile;

class TarCompressionOfFileTest extends TestCase
{
    private string $root ;

    /**
     * @throws RandomException
     */
    protected function setUp() :void
    {
        $this->root = sys_get_temp_dir() . '/oihana-tar-compression-' . bin2hex( random_bytes( 6 ) ) ;
        mkdir( $this->root , 0777 , true ) ;
    }

    protected function tearDown() :void
    {
        foreach ( glob( $this->root . '/*' ) ?: [] as $file )
        {
            unlink( $file ) ;
        }

        rmdir( $this->root ) ;
    }

    /**
     * The magic bytes are written by hand rather than produced with `gzencode()` or
     * `bzcompress()`: the function reads three bytes and must be testable on a host
     * without ext-zlib or ext-bz2.
     */
    private function file( string $name , string $contents ) :string
    {
        $path = $this->root . '/' . $name ;
        file_put_contents( $path , $contents ) ;
        return $path ;
    }

    public function testGzipIsRecognised(): void
    {
        $this->assertSame
        (
            CompressionType::GZIP ,
            tarCompressionOfFile( $this->file( 'a.tar.gz' , "\x1f\x8b\x08" . str_repeat( "\0" , 40 ) ) )
        ) ;
    }

    public function testBzip2IsRecognised(): void
    {
        $this->assertSame
        (
            CompressionType::BZIP2 ,
            tarCompressionOfFile( $this->file( 'a.tar.bz2' , 'BZh9' . str_repeat( "\0" , 40 ) ) )
        ) ;
    }

    public function testXzIsRecognised(): void
    {
        $this->assertSame
        (
            CompressionType::XZ ,
            tarCompressionOfFile( $this->file( 'a.tar.xz' , "\xfd7zXZ\x00" . str_repeat( "\0" , 40 ) ) )
        ) ;
    }

    public function testZstdIsRecognised(): void
    {
        $this->assertSame
        (
            CompressionType::ZSTD ,
            tarCompressionOfFile( $this->file( 'a.tar.zst' , "\x28\xb5\x2f\xfd" . str_repeat( "\0" , 40 ) ) )
        ) ;
    }

    /**
     * xz's magic is six bytes, the longest of the four, so the read has to be long enough
     * for it — and a file of five bytes must not be mistaken for one.
     */
    public function testAFileShorterThanTheXzMagicIsNotXz(): void
    {
        $this->assertNull( tarCompressionOfFile( $this->file( 'short.xz' , "\xfd7zX" ) ) ) ;
    }

    public function testAnUncompressedArchiveReportsNoCompression(): void
    {
        $this->assertNull( tarCompressionOfFile( $this->file( 'a.tar' , str_repeat( "\0" , 512 ) ) ) ) ;
    }

    /**
     * The whole point of reading the bytes rather than the name: an archive handed to
     * `untar()` may be called anything, and the engine has to be told nothing it cannot
     * verify.
     */
    public function testTheNameIsNotConsulted(): void
    {
        $lying = $this->file( 'named.tar.gz' , str_repeat( "\0" , 512 ) ) ;

        $this->assertNull( tarCompressionOfFile( $lying ) , 'A plain tar called .tar.gz is still a plain tar' ) ;

        $honest = $this->file( 'named.tar' , "\x1f\x8b\x08" . str_repeat( "\0" , 40 ) ) ;

        $this->assertSame( CompressionType::GZIP , tarCompressionOfFile( $honest ) , 'And a gzip called .tar is still gzip' ) ;
    }

    public function testAMissingFileReportsNoCompression(): void
    {
        $this->assertNull( tarCompressionOfFile( $this->root . '/absent.tar.gz' ) ) ;
    }

    public function testAFileShorterThanTheMagicReportsNoCompression(): void
    {
        $this->assertNull( tarCompressionOfFile( $this->file( 'tiny' , "\x1f" ) ) ) ;
    }

    public function testAnEmptyFileReportsNoCompression(): void
    {
        $this->assertNull( tarCompressionOfFile( $this->file( 'empty' , '' ) ) ) ;
    }
}
