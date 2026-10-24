<?php

namespace tests\oihana\files\archive\tar;

use oihana\files\enums\CompressionType;
use PHPUnit\Framework\TestCase;

use function oihana\files\archive\tar\tarBinary;
use function oihana\files\archive\tar\tarCompressorExists;
use function oihana\files\archive\tar\untarBinaryHandles;

class UntarBinaryHandlesTest extends TestCase
{
    private string|false $override ;
    private string $root ;

    protected function setUp() :void
    {
        $this->override = getenv( 'OIHANA_TAR_BINARY' ) ;
        $this->root     = sys_get_temp_dir() . '/oihana-untar-handles-' . bin2hex( random_bytes( 6 ) ) ;
        mkdir( $this->root , 0777 , true ) ;
    }

    protected function tearDown() :void
    {
        putenv( $this->override === false ? 'OIHANA_TAR_BINARY' : 'OIHANA_TAR_BINARY=' . $this->override ) ;
        tarBinary( refresh: true ) ;

        foreach ( glob( $this->root . '/*' ) ?: [] as $file )
        {
            unlink( $file ) ;
        }

        rmdir( $this->root ) ;
    }

    private function file( string $name , string $contents ) :string
    {
        $path = $this->root . '/' . $name ;
        file_put_contents( $path , $contents ) ;
        return $path ;
    }

    private function plainTar() :string
    {
        return $this->file( 'plain.tar' , str_repeat( "\0" , 1024 ) ) ;
    }

    private function gzipTar() :string
    {
        return $this->file( 'compressed.tar.gz' , "\x1f\x8b\x08" . str_repeat( "\0" , 40 ) ) ;
    }

    /**
     * An uncompressed archive asks nothing of the host beyond `tar` itself.
     */
    public function testAnUncompressedArchiveNeedsTheBinaryAlone(): void
    {
        putenv( 'OIHANA_TAR_BINARY' ) ;

        if ( tarBinary( refresh: true ) === null )
        {
            $this->markTestSkipped( 'No GNU tar on this host.' ) ;
        }

        $this->assertTrue( untarBinaryHandles( $this->plainTar() ) ) ;
    }

    /**
     * A compressed one also needs the program `tar` pipes through. `ext-zlib` does not
     * help here: it is PharData that decompresses in PHP, not `tar`.
     */
    public function testACompressedArchiveAlsoNeedsItsDecompressor(): void
    {
        putenv( 'OIHANA_TAR_BINARY' ) ;

        if ( tarBinary( refresh: true ) === null )
        {
            $this->markTestSkipped( 'No GNU tar on this host.' ) ;
        }

        $this->assertSame
        (
            tarCompressorExists( CompressionType::GZIP ) ,
            untarBinaryHandles( $this->gzipTar() ) ,
            'The answer must follow the presence of gzip, not be assumed'
        ) ;
    }

    /**
     * The seam the extraction suite runs on: an override declared empty means "do not
     * use one", so PharData takes every archive.
     */
    public function testAnEmptyOverrideForcesPharData(): void
    {
        putenv( 'OIHANA_TAR_BINARY=' ) ;
        tarBinary( refresh: true ) ;

        $this->assertFalse( untarBinaryHandles( $this->plainTar() ) ) ;
        $this->assertFalse( untarBinaryHandles( $this->gzipTar() ) ) ;
    }

    public function testAnOverrideThatIsNotATarForcesPharData(): void
    {
        putenv( 'OIHANA_TAR_BINARY=' . $this->root . '/there-is-no-tar-here' ) ;
        tarBinary( refresh: true ) ;

        $this->assertFalse( untarBinaryHandles( $this->plainTar() ) ) ;
    }
}
