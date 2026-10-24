<?php

namespace tests\oihana\files\archive\tar;

use oihana\files\enums\CompressionType;
use oihana\files\enums\TarInfo;
use oihana\files\exceptions\UnsupportedCompressionException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function oihana\files\archive\tar\assertTar;
use function oihana\files\archive\tar\tar;
use function oihana\files\archive\tar\tarBinary;
use function oihana\files\archive\tar\tarCompressionOfFile;
use function oihana\files\archive\tar\tarFileInfo;
use function oihana\files\archive\tar\tarIsCompressed;
use function oihana\files\archive\tar\tarWritableCompressions;
use function oihana\files\archive\tar\untar;
use function oihana\files\deleteDirectory;

/**
 * Every compression this library writes, read back.
 *
 * `xz` and `zstd` arrived in 1.5.0, and the lot that added them was mostly not about
 * writing: `tar` had carried `--xz` for two releases and gained `--zstd` in one line.
 * What took the work was the other direction — `hasTarExtension`, `hasTarMimeType`,
 * `tarIsCompressed`, `tarCompressionOfFile`, `tarFileInfo` and `untar` each had to learn
 * the two formats, because a format written and not read back is the defect the previous
 * lot had just spent itself fixing.
 *
 * So this file asserts the loop, not the writing: produced, recognised, and restored byte
 * for byte.
 */
class TarCompressionRoundTripTest extends TestCase
{
    private string|false $override ;
    private string|false $path ;
    private string $root ;

    protected function setUp() :void
    {
        $this->override = getenv( 'OIHANA_TAR_BINARY' ) ;
        $this->path     = getenv( 'PATH' ) ;
        $this->root     = sys_get_temp_dir() . '/oihana-tar-roundtrip-' . bin2hex( random_bytes( 6 ) ) ;

        putenv( 'OIHANA_TAR_BINARY' ) ;
        tarBinary( refresh: true ) ;

        mkdir( $this->root . '/tree/wp-content' , 0777 , true ) ;

        file_put_contents( $this->root . '/tree/index.php' , str_repeat( 'une page' , 400 ) ) ;
        file_put_contents( $this->root . '/tree/wp-content/mon document.txt' , "l'été, en pièce jointe" ) ;
    }

    protected function tearDown() :void
    {
        putenv( $this->override === false ? 'OIHANA_TAR_BINARY' : 'OIHANA_TAR_BINARY=' . $this->override ) ;
        putenv( $this->path === false ? 'PATH' : 'PATH=' . $this->path ) ;

        tarBinary( refresh: true ) ;

        deleteDirectory( $this->root ) ;
    }

    public static function compressionProvider() :array
    {
        return
        [
            'none'  => [ CompressionType::NONE  , '.tar'      , null                    ] ,
            'gzip'  => [ CompressionType::GZIP  , '.tar.gz'   , CompressionType::GZIP   ] ,
            'bzip2' => [ CompressionType::BZIP2 , '.tar.bz2'  , CompressionType::BZIP2  ] ,
            'xz'    => [ CompressionType::XZ    , '.tar.xz'   , CompressionType::XZ     ] ,
            'zstd'  => [ CompressionType::ZSTD  , '.tar.zst'  , CompressionType::ZSTD   ] ,
        ] ;
    }

    #[DataProvider('compressionProvider')]
    public function testAnArchiveIsWrittenRecognisedAndRestored( string $compression , string $extension , ?string $detected ): void
    {
        if ( !in_array( $compression , tarWritableCompressions() , true ) )
        {
            $this->markTestSkipped( sprintf( 'This host cannot write %s.' , $compression ) ) ;
        }

        $archive = tar( [ $this->root . '/tree' ] , $this->root . '/site' . $extension , $compression , $this->root ) ;

        $this->assertFileExists( $archive ) ;
        $this->assertStringEndsWith( $extension , $archive ) ;

        // Recognised as a tar archive: extension, MIME type, and the bytes themselves.
        $this->assertTrue( assertTar( $archive ) , 'assertTar must accept it, or untar() will not read it' ) ;
        $this->assertSame( $detected , tarCompressionOfFile( $archive ) ) ;
        $this->assertSame( $detected !== null , tarIsCompressed( $archive ) ) ;

        $this->assertSame
        (
            $detected ?? CompressionType::NONE ,
            tarFileInfo( $archive )[ TarInfo::COMPRESSION ] ,
            'tarFileInfo must report the compression it was written with'
        ) ;

        // And restored.
        $out = $this->root . '/out' ;
        $this->assertTrue( untar( $archive , $out ) ) ;

        $this->assertSame
        (
            file_get_contents( $this->root . '/tree/index.php' ) ,
            file_get_contents( $out . '/tree/index.php' )
        ) ;

        $this->assertSame
        (
            "l'été, en pièce jointe" ,
            file_get_contents( $out . '/tree/wp-content/mon document.txt' ) ,
            'An accented name with a space must come back as it went in'
        ) ;
    }

    /**
     * With no output path, `tar()` names the archive itself — and the name has to carry the
     * compression, or nothing downstream recognises what it is holding:
     * `hasTarExtension()` reads the name, and `untar()` will not touch a file `assertTar()`
     * rejected.
     */
    #[DataProvider('compressionProvider')]
    public function testAnUnnamedArchiveIsNamedAfterItsCompression( string $compression , string $extension , ?string $detected ): void
    {
        if ( !in_array( $compression , tarWritableCompressions() , true ) )
        {
            $this->markTestSkipped( sprintf( 'This host cannot write %s.' , $compression ) ) ;
        }

        $archive = tar( [ $this->root . '/tree' ] , null , $compression , $this->root ) ;

        try
        {
            $this->assertStringEndsWith( $extension , $archive ) ;
            $this->assertTrue( assertTar( $archive ) ) ;
            $this->assertSame( $detected , tarCompressionOfFile( $archive ) ) ;
        }
        finally
        {
            @unlink( $archive ) ;
        }
    }

    /**
     * The fix this lot needed before `xz` could work at all.
     *
     * `proc_open()` given an environment replaces it, so the `tar` child had no `PATH` and
     * its shell fell back to a built-in default — `gzip` under `/usr/bin` was found,
     * anything installed elsewhere was not. Here the `PATH` holds **one** directory, which
     * no default would ever include, and the compressor in it is a stand-in. If the child
     * is not given this process's `PATH`, `tar` reports `xz: command not found`.
     */
    public function testTheCompressorIsFoundOnThisProcessPath(): void
    {
        if ( tarBinary() === null )
        {
            $this->markTestSkipped( 'No GNU tar on this host.' ) ;
        }

        $bin = $this->root . '/only-here' ;
        mkdir( $bin , 0755 ) ;

        // Resolved now, while there is still a PATH to resolve it on, and written into the
        // stand-in as an absolute path: in a moment `cat` will not be findable either.
        $cat = trim( (string) shell_exec( 'command -v cat' ) ) ;

        $this->assertNotSame( '' , $cat , 'The stand-in compressor needs a cat to be a filter' ) ;

        file_put_contents( $bin . '/xz' , sprintf( "#!/bin/sh\nexec %s\n" , $cat ) ) ;
        chmod( $bin . '/xz' , 0755 ) ;

        putenv( 'PATH=' . $bin ) ;

        $archive = tar( [ $this->root . '/tree' ] , $this->root . '/site.tar.xz' , CompressionType::XZ , $this->root ) ;

        $this->assertFileExists( $archive ) ;
        $this->assertGreaterThan( 0 , filesize( $archive ) ) ;
    }

    // -----------------------------------------------------------------
    // What is refused, and in what words
    // -----------------------------------------------------------------

    public static function unwrittenProvider() :array
    {
        return
        [
            'lz4 has no tar flag'      => [ CompressionType::LZ4 ] ,
            'lzma was superseded by xz' => [ CompressionType::LZMA ] ,
        ] ;
    }

    #[DataProvider('unwrittenProvider')]
    public function testACompressionThisLibraryDoesNotWriteIsRefusedByName( string $compression ): void
    {
        try
        {
            tar( [ $this->root . '/tree' ] , $this->root . '/site.tar' , $compression , $this->root ) ;
            $this->fail( sprintf( '%s must be refused.' , $compression ) ) ;
        }
        catch ( UnsupportedCompressionException $e )
        {
            $this->assertStringContainsString( 'does not write ' . $compression , $e->getMessage() ) ;
            $this->assertStringContainsString( 'This host writes:' , $e->getMessage() , 'and say what would work' ) ;
        }
    }

    /**
     * The other half of the refusal, and the one that used to be said wrong: `xz` is not a
     * format this library refuses, it is a format **this host** cannot produce. The old
     * message — *"Compression type 'xz' is not supported"* — came out of a helper
     * assembling a temporary path, seventeen lines before the engines were looked at.
     */
    public function testAFormatThisHostCannotProduceNamesWhatIsMissing(): void
    {
        $empty = $this->root . '/empty-bin' ;
        mkdir( $empty , 0700 ) ;

        putenv( 'PATH=' . $empty ) ;

        try
        {
            tar( [ $this->root . '/tree' ] , $this->root . '/site.tar.xz' , CompressionType::XZ , $this->root ) ;
            $this->fail( 'Without the xz program, xz must be refused.' ) ;
        }
        catch ( UnsupportedCompressionException $e )
        {
            $this->assertStringContainsString( 'needs the system GNU tar and its xz program' , $e->getMessage() ) ;
            $this->assertStringNotContainsString( 'is not supported' , $e->getMessage() , 'The old wording blamed the library' ) ;
        }
    }

    /**
     * And reading one where there is no GNU tar. `PharData` decompresses through PHP
     * extensions, and PHP has none for xz or zstd — so this is said plainly instead of
     * coming out of `Phar` as a sentence about a format nobody mentioned.
     */
    public function testReadingAZstdArchiveWithoutABinaryNamesWhatIsMissing(): void
    {
        if ( !in_array( CompressionType::ZSTD , tarWritableCompressions() , true ) )
        {
            $this->markTestSkipped( 'This host cannot write zstd, so it cannot stage the fixture.' ) ;
        }

        $archive = tar( [ $this->root . '/tree' ] , $this->root . '/site.tar.zst' , CompressionType::ZSTD , $this->root ) ;

        putenv( 'OIHANA_TAR_BINARY=' ) ;
        tarBinary( refresh: true ) ;

        try
        {
            untar( $archive , $this->root . '/out' ) ;
            $this->fail( 'PharData cannot read a zstd archive.' ) ;
        }
        catch ( RuntimeException $e )
        {
            $this->assertStringContainsString( 'this is a zstd archive' , $e->getMessage() ) ;
            $this->assertStringContainsString( 'PharData reads gzip and bzip2 only' , $e->getMessage() ) ;
        }
    }
}
