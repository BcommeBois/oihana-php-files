<?php

namespace tests\oihana\files\archive\tar;

use oihana\files\enums\CompressionType;
use PHPUnit\Framework\TestCase;

use function oihana\files\archive\tar\tarBinary;
use function oihana\files\archive\tar\tarCompressorExists;
use function oihana\files\archive\tar\tarWritableCompressions;

class TarWritableCompressionsTest extends TestCase
{
    private string|false $override ;
    private string|false $path ;
    private string $root ;

    protected function setUp() :void
    {
        $this->override = getenv( 'OIHANA_TAR_BINARY' ) ;
        $this->path     = getenv( 'PATH' ) ;
        $this->root     = sys_get_temp_dir() . '/oihana-tar-writable-' . bin2hex( random_bytes( 6 ) ) ;

        mkdir( $this->root , 0777 , true ) ;
    }

    protected function tearDown() :void
    {
        putenv( $this->override === false ? 'OIHANA_TAR_BINARY' : 'OIHANA_TAR_BINARY=' . $this->override ) ;
        putenv( $this->path === false ? 'PATH' : 'PATH=' . $this->path ) ;

        tarBinary( refresh: true ) ;

        if ( is_dir( $this->root ) )
        {
            rmdir( $this->root ) ;
        }
    }

    public function testUncompressedIsAlwaysWritable(): void
    {
        $this->assertContains( CompressionType::NONE , tarWritableCompressions() ) ;
    }

    /**
     * Without a binary, only what `Phar` can compress through PHP extensions is left — and
     * `Phar` knows gzip and bzip2 and nothing else, so `xz` and `zstd` disappear.
     */
    public function testWithoutABinaryOnlyWhatPharCanCompressRemains(): void
    {
        putenv( 'OIHANA_TAR_BINARY=' ) ;
        tarBinary( refresh: true ) ;

        $writable = tarWritableCompressions() ;

        $this->assertNotContains( CompressionType::XZ , $writable ) ;
        $this->assertNotContains( CompressionType::ZSTD , $writable ) ;
        $this->assertSame( [ CompressionType::NONE , CompressionType::GZIP , CompressionType::BZIP2 ] , $writable ) ;
    }

    /**
     * And with a binary, the answer follows the programs on the `PATH` rather than any
     * assumption: `tar` pipes through them.
     */
    public function testWithABinaryTheAnswerFollowsTheProgramsOnThePath(): void
    {
        putenv( 'OIHANA_TAR_BINARY' ) ;

        if ( tarBinary( refresh: true ) === null )
        {
            $this->markTestSkipped( 'No GNU tar on this host.' ) ;
        }

        $writable = tarWritableCompressions() ;

        $this->assertSame
        (
            tarCompressorExists( CompressionType::XZ ) ,
            in_array( CompressionType::XZ , $writable , true )
        ) ;

        $this->assertSame
        (
            tarCompressorExists( CompressionType::ZSTD ) ,
            in_array( CompressionType::ZSTD , $writable , true )
        ) ;
    }

    /**
     * A GNU tar with nothing to pipe through adds nothing. The host still writes what
     * `Phar` can, which is why the fallback is not a regression.
     */
    public function testABinaryWithNoCompressorAddsNothing(): void
    {
        $empty = $this->root . '/empty-bin' ;
        mkdir( $empty , 0700 ) ;

        putenv( 'OIHANA_TAR_BINARY' ) ;

        if ( tarBinary( refresh: true ) === null )
        {
            $this->markTestSkipped( 'No GNU tar on this host.' ) ;
        }

        putenv( 'PATH=' . $empty ) ;

        $writable = tarWritableCompressions() ;

        $this->assertNotContains( CompressionType::XZ , $writable ) ;
        $this->assertNotContains( CompressionType::ZSTD , $writable ) ;
        $this->assertContains( CompressionType::NONE , $writable ) ;

        rmdir( $empty ) ;
    }

    public function testNothingIsListedTwice(): void
    {
        $writable = tarWritableCompressions() ;

        $this->assertSame( $writable , array_values( array_unique( $writable ) ) ) ;
    }

    /**
     * Never promised, and the reasons differ: `xz` superseded lzma, and GNU tar has no
     * `--lz4` flag at all.
     */
    public function testLzmaAndLz4AreNotPromised(): void
    {
        $writable = tarWritableCompressions() ;

        $this->assertNotContains( CompressionType::LZMA , $writable ) ;
        $this->assertNotContains( CompressionType::LZ4 , $writable ) ;
    }
}
