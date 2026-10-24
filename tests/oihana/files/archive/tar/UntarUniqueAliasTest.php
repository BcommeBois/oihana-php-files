<?php

namespace tests\oihana\files\archive\tar;

use oihana\files\enums\CompressionType;
use oihana\files\exceptions\UnsupportedCompressionException;
use PHPUnit\Framework\TestCase;

use function oihana\files\archive\tar\untarUniqueAlias;

class UntarUniqueAliasTest extends TestCase
{
    private string $root ;
    private string $archive ;

    protected function setUp() :void
    {
        $this->root    = sys_get_temp_dir() . '/oihana-untar-alias-' . bin2hex( random_bytes( 6 ) ) ;
        $this->archive = $this->root . '/site.tar.gz' ;

        mkdir( $this->root , 0777 , true ) ;
        file_put_contents( $this->archive , "\x1f\x8b\x08" . str_repeat( "\0" , 40 ) ) ;
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
     * A hard link, not a copy: the point is to pay nothing for a 2 GB archive.
     */
    public function testTheAliasIsTheSameFileUnderAnotherName(): void
    {
        $alias = untarUniqueAlias( $this->archive , CompressionType::GZIP ) ;

        $this->assertNotNull( $alias ) ;
        $this->assertFileExists( $alias ) ;
        $this->assertSame( fileinode( $this->archive ) , fileinode( $alias ) , 'A hard link shares the inode' ) ;
        $this->assertSame( file_get_contents( $this->archive ) , file_get_contents( $alias ) ) ;
    }

    public function testTwoCallsNeverGiveTheSameName(): void
    {
        $first  = untarUniqueAlias( $this->archive , CompressionType::GZIP ) ;
        $second = untarUniqueAlias( $this->archive , CompressionType::GZIP ) ;

        $this->assertNotSame( $first , $second , 'Which is the whole point: the .tar inherits this name' ) ;
    }

    /**
     * The extension has to match, because `decompress()` strips it to name the `.tar`.
     */
    public function testTheAliasCarriesTheExtensionOfItsCompression(): void
    {
        $this->assertStringEndsWith( '.tar.gz' , untarUniqueAlias( $this->archive , CompressionType::GZIP ) ) ;
        $this->assertStringEndsWith( '.tar.bz2' , untarUniqueAlias( $this->archive , CompressionType::BZIP2 ) ) ;
    }

    /**
     * Beside the archive, because a hard link cannot cross a filesystem — and because
     * that is already where `decompress()` writes.
     */
    public function testTheAliasSitsBesideTheArchive(): void
    {
        $this->assertSame( $this->root , dirname( untarUniqueAlias( $this->archive , CompressionType::GZIP ) ) ) ;
    }

    public function testACompressionWithNoKnownExtensionIsRefused(): void
    {
        $this->expectException( UnsupportedCompressionException::class ) ;

        untarUniqueAlias( $this->archive , CompressionType::XZ ) ;
    }
}
