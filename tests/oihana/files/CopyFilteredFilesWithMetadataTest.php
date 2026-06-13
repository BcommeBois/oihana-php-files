<?php

namespace oihana\files ;

use PHPUnit\Framework\TestCase;
use RuntimeException;

use oihana\files\exceptions\DirectoryException;

class CopyFilteredFilesWithMetadataTest extends TestCase
{
    private string $sourceDir ;
    private string $destDir ;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'oihana-php-files/tests/files/copy-filtered-meta-' . uniqid() ;
        $this->sourceDir = $base . DIRECTORY_SEPARATOR . 'source' ;
        $this->destDir   = $base . DIRECTORY_SEPARATOR . 'dest' ;

        mkdir( $this->sourceDir , 0777 , true );
        mkdir( $this->destDir , 0777 , true );

        file_put_contents( $this->sourceDir . '/keep.txt' , 'keep' );
        file_put_contents( $this->sourceDir . '/skip.log' , 'skip' );
    }

    /**
     * @throws DirectoryException
     */
    protected function tearDown(): void
    {
        deleteDirectory( dirname( $this->sourceDir ) );
    }

    public function testCopiesFilteredFiles(): void
    {
        copyFilteredFilesWithMetadata( $this->sourceDir , $this->destDir , [ '*.log' ] );

        $this->assertFileExists( $this->destDir . '/keep.txt' );
        $this->assertFileDoesNotExist( $this->destDir . '/skip.log' );
        $this->assertFileDoesNotExist( $this->destDir . '/.metadata.json' );
    }

    public function testWritesMetadataFile(): void
    {
        $metadata = [ 'version' => '1.0.0' , 'author' => 'ekameleon' ];

        copyFilteredFilesWithMetadata( $this->sourceDir , $this->destDir , [] , null , $metadata );

        $this->assertFileExists( $this->destDir . '/.metadata.json' );
        $decoded = json_decode( file_get_contents( $this->destDir . '/.metadata.json' ) , true );
        $this->assertSame( $metadata , $decoded );
    }

    public function testMetadataMakesEmptyResultValid(): void
    {
        // Everything is excluded, but the metadata keeps the destination non-empty (no throw).
        copyFilteredFilesWithMetadata( $this->sourceDir , $this->destDir , [ '*' ] , null , [ 'k' => 'v' ] );

        $this->assertFileExists( $this->destDir . '/.metadata.json' );
    }

    public function testThrowsWhenNothingMatchesAndNoMetadata(): void
    {
        $this->expectException( RuntimeException::class );
        $this->expectExceptionMessage( 'No files match the filtering criteria.' );

        copyFilteredFilesWithMetadata( $this->sourceDir , $this->destDir , [ '*' ] );
    }
}
