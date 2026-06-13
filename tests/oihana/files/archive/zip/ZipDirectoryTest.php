<?php

namespace oihana\files\archive\zip;

use ZipArchive;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use oihana\files\enums\CompressionType;
use oihana\files\enums\ZipOption;
use oihana\files\exceptions\DirectoryException;
use oihana\files\exceptions\FileException;
use oihana\files\exceptions\UnsupportedCompressionException;
use RuntimeException;

use function oihana\files\deleteDirectory;

class ZipDirectoryTest extends TestCase
{
    private string $baseTempDir ;
    private string $sourceDir ;
    private string $outputDir ;

    protected function setUp(): void
    {
        $this->baseTempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'oihana-php-files/tests/files/archive/zip-directory-tests-' . uniqid() ;

        $this->sourceDir = $this->baseTempDir . DIRECTORY_SEPARATOR . 'source' ;
        $this->outputDir = $this->baseTempDir . DIRECTORY_SEPARATOR . 'output' ;

        mkdir( $this->sourceDir , 0777 , true ) ;
        mkdir( $this->outputDir , 0777 , true ) ;

        file_put_contents( $this->sourceDir . '/file1.txt' , 'content1' );
        file_put_contents( $this->sourceDir . '/file2.log' , 'log_content' );
        mkdir( $this->sourceDir . '/subdir' );
        file_put_contents( $this->sourceDir . '/subdir/file3.ini' , 'config' );
        mkdir( $this->sourceDir . '/empty_dir' );
        mkdir( $this->sourceDir . '/logs' );
        file_put_contents( $this->sourceDir . '/logs/app.log' , 'app_log' );
    }

    /**
     * @throws DirectoryException
     */
    protected function tearDown(): void
    {
        deleteDirectory( $this->baseTempDir );
    }

    /**
     * @return string[]
     */
    private function entries( string $archive ): array
    {
        $zip = new ZipArchive();
        $zip->open( $archive );
        $names = [];
        for ( $i = 0 ; $i < $zip->numFiles ; $i++ )
        {
            $names[] = $zip->getNameIndex( $i );
        }
        $zip->close();
        return $names;
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     * @throws UnsupportedCompressionException
     */
    #[Test]
    public function it_archives_directory_with_default_settings(): void
    {
        $archive      = zipDirectory( $this->sourceDir );
        $expectedPath = dirname( $this->sourceDir ) . DIRECTORY_SEPARATOR . basename( $this->sourceDir ) . '.zip';

        $this->assertSame( $expectedPath , $archive );
        $this->assertFileExists( $archive );

        $entries = $this->entries( $archive );
        $this->assertContains( 'file1.txt' , $entries );
        $this->assertContains( 'subdir/file3.ini' , $entries );
        $this->assertContains( 'empty_dir/' , $entries );
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     * @throws UnsupportedCompressionException
     */
    #[Test]
    public function it_uses_custom_output_path(): void
    {
        $output  = $this->outputDir . DIRECTORY_SEPARATOR . 'my-custom-archive.zip';
        $archive = zipDirectory( $this->sourceDir , CompressionType::NONE , $output );

        $this->assertSame( $output , $archive );
        $this->assertFileExists( $output );
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     * @throws UnsupportedCompressionException
     */
    #[Test]
    public function it_excludes_files_based_on_pattern(): void
    {
        $output  = $this->outputDir . DIRECTORY_SEPARATOR . 'archive_excluded.zip';
        $archive = zipDirectory( $this->sourceDir , CompressionType::ZIP , $output , [ ZipOption::EXCLUDE => [ '*.log' , 'logs/' ] ] );

        $entries = $this->entries( $archive );
        $this->assertContains( 'file1.txt' , $entries );
        $this->assertContains( 'subdir/file3.ini' , $entries );
        $this->assertNotContains( 'file2.log' , $entries );
        $this->assertNotContains( 'logs/app.log' , $entries );
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     * @throws UnsupportedCompressionException
     */
    #[Test]
    public function it_filters_files_using_callback(): void
    {
        $output  = $this->outputDir . DIRECTORY_SEPARATOR . 'archive_filtered.zip';
        $archive = zipDirectory
        (
            $this->sourceDir ,
            CompressionType::ZIP ,
            $output ,
            [ ZipOption::FILTER => fn( string $filepath ): bool => str_ends_with( $filepath , '.txt' ) ]
        );

        $entries = $this->entries( $archive );
        $this->assertContains( 'file1.txt' , $entries );
        $this->assertNotContains( 'file2.log' , $entries );
        $this->assertNotContains( 'subdir/file3.ini' , $entries );
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     * @throws UnsupportedCompressionException
     */
    #[Test]
    public function it_adds_metadata_file(): void
    {
        $metadata = [ 'version' => '1.0.0' , 'build' => '20250717' ];
        $output   = $this->outputDir . DIRECTORY_SEPARATOR . 'archive_with_meta.zip';
        $archive  = zipDirectory( $this->sourceDir , CompressionType::ZIP , $output , [ ZipOption::METADATA => $metadata ] );

        $zip = new ZipArchive();
        $zip->open( $archive );
        $decodedMeta = json_decode( $zip->getFromName( '.metadata.json' ) , true );
        $zip->close();

        $this->assertEquals( $metadata , $decodedMeta );
        $this->assertContains( 'file1.txt' , $this->entries( $archive ) );
    }

    /**
     * @throws UnsupportedCompressionException
     * @throws FileException
     */
    #[Test]
    public function it_throws_exception_if_source_directory_does_not_exist(): void
    {
        $this->expectException( DirectoryException::class );
        zipDirectory( '/non/existent/path' );
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     * @throws UnsupportedCompressionException
     */
    #[Test]
    public function it_throws_exception_if_no_files_match_filters(): void
    {
        $this->expectException( RuntimeException::class );
        $this->expectExceptionMessage( 'No files match the filtering criteria.' );
        zipDirectory( $this->sourceDir , CompressionType::ZIP , null , [ ZipOption::EXCLUDE => [ '*' ] ] );
    }
}
