<?php

namespace oihana\files\archive\tar;

use oihana\enums\Char;
use oihana\files\enums\CompressionType;
use oihana\files\enums\FileExtension;
use oihana\files\enums\TarOption;
use oihana\files\exceptions\DirectoryException;
use oihana\files\exceptions\FileException;
use oihana\files\exceptions\UnsupportedCompressionException;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PharData;
use RuntimeException;
use function oihana\files\deleteDirectory;
use function oihana\files\makeDirectory;

class TarDirectoryTest extends TestCase
{
    private string $baseTempDir ;
    private string $sourceDir ;
    private string $outputDir ;

    /**
     * Crée une arborescence de fichiers temporaire avant chaque test.
     */
    protected function setUp(): void
    {
        $this->baseTempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'oihana-php-files/tests/files/archive/tar-directory-tests-' . uniqid() ;

        $this->sourceDir = $this->baseTempDir . DIRECTORY_SEPARATOR . 'source' ;
        $this->outputDir = $this->baseTempDir . DIRECTORY_SEPARATOR . 'output' ;

        mkdir( $this->sourceDir , 0777 , true ) ;
        mkdir( $this->outputDir , 0777 , true ) ;

        file_put_contents($this->sourceDir . '/file1.txt', 'content1');
        file_put_contents($this->sourceDir . '/file2.log', 'log_content');
        mkdir($this->sourceDir . '/subdir');
        file_put_contents($this->sourceDir . '/subdir/file3.ini', 'config');
        mkdir($this->sourceDir . '/empty_dir');
        mkdir($this->sourceDir . '/logs');
        file_put_contents($this->sourceDir . '/logs/app.log', 'app_log');
    }

    /**
     * Nettoie les répertoires temporaires après chaque test.
     * @throws DirectoryException
     */
    protected function tearDown(): void
    {
        deleteDirectory( $this->baseTempDir );
    }

    /**
     * @throws UnsupportedCompressionException
     * @throws DirectoryException
     * @throws FileException
     */
    #[Test]
    public function it_archives_directory_with_default_settings(): void
    {
        $archivePath  = tarDirectory( $this->sourceDir );
        $expectedPath = dirname( $this->sourceDir) . DIRECTORY_SEPARATOR . basename( $this->sourceDir ) . '.tar.gz';

        $this->assertSame( $expectedPath , $archivePath ) ;
        $this->assertFileExists( $archivePath ) ;

        $extractDir = $this->outputDir . DIRECTORY_SEPARATOR . 'extract' ;

        makeDirectory( $extractDir );

        $phar = new PharData( $archivePath ) ;

        $phar->extractTo( $extractDir );
        $this->assertFileExists      ($extractDir . '/file1.txt'        ) ;
        $this->assertFileExists      ($extractDir . '/subdir/file3.ini' ) ;

        deleteDirectory( $extractDir ) ;

        // Check the empty folder in the archive (must use a special hook with PharData !)

        $expectedKey = "empty_dir" ;

        $this->assertTrue
        (
            $phar->offsetExists( $expectedKey ) ,
            "The archive should contain the empty directory '$expectedKey'."
        );

        $this->assertTrue
        (
            $phar[ $expectedKey ]->isDir(),
            "The entry '$expectedKey' should be a directory."
        );
    }

    /**
     * @throws UnsupportedCompressionException
     * @throws DirectoryException
     * @throws FileException
     */
    #[Test]
    #[DataProvider('compressionProvider')]
    public function it_archives_with_different_compression_types(string $compression, string $expectedExtension): void
    {
        $outputPath = $this->outputDir . DIRECTORY_SEPARATOR . 'archive' . $expectedExtension;

        // Act
        $archivePath = tarDirectory($this->sourceDir, $compression, $outputPath);

        // Assert
        $this->assertSame($outputPath, $archivePath);
        $this->assertFileExists($archivePath);
    }

    public static function compressionProvider(): iterable
    {
        yield 'GZIP compression'  => [ CompressionType::GZIP  , FileExtension::GZ  ] ;
        yield 'BZIP2 compression' => [ CompressionType::BZIP2 , FileExtension::BZ2 ] ;
        yield 'No compression'    => [ CompressionType::NONE  , Char::EMPTY ];
    }

    /**
     * @throws UnsupportedCompressionException
     * @throws DirectoryException
     * @throws FileException
     */
    #[Test]
    public function it_uses_custom_output_path(): void
    {
        $outputPath = $this->outputDir . DIRECTORY_SEPARATOR . 'my-custom-archive.tar.gz';

        // Act
        $archivePath = tarDirectory($this->sourceDir, CompressionType::GZIP, $outputPath);

        // Assert
        $this->assertSame($outputPath, $archivePath);
        $this->assertFileExists($outputPath);
    }

    /**
     * @throws UnsupportedCompressionException
     * @throws DirectoryException
     * @throws FileException
     */
    #[Test]
    public function it_excludes_files_based_on_pattern(): void
    {
        $options = [
            TarOption::EXCLUDE => ['*.log', 'logs/']
        ];

        $outputPath = $this->outputDir . DIRECTORY_SEPARATOR . 'archive_excluded.tar.gz';

        // Act
        $archivePath = tarDirectory($this->sourceDir, CompressionType::GZIP, $outputPath, $options);

        // Assert
        $this->assertFileExists($archivePath);

        $extractDir = $this->outputDir . DIRECTORY_SEPARATOR . 'extract_excluded';
        $phar = new PharData($archivePath);
        $phar->extractTo($extractDir);

        $this->assertFileExists($extractDir . '/file1.txt');
        $this->assertFileExists($extractDir . '/subdir/file3.ini');
        $this->assertFileDoesNotExist($extractDir . '/file2.log');
        $this->assertDirectoryDoesNotExist($extractDir . '/logs');
    }

    /**
     * @throws UnsupportedCompressionException
     * @throws DirectoryException
     * @throws FileException
     */
    #[Test]
    public function it_filters_files_using_callback(): void
    {
        $options = [
            TarOption::FILTER => fn(string $filepath): bool => str_ends_with($filepath, '.txt')
        ];

        $outputPath = $this->outputDir . DIRECTORY_SEPARATOR . 'archive_filtered.tar.gz';

        // Act
        $archivePath = tarDirectory($this->sourceDir, CompressionType::GZIP, $outputPath, $options);

        // Assert
        $extractDir = $this->outputDir . DIRECTORY_SEPARATOR . 'extract_filtered';
        $phar = new PharData($archivePath);
        $phar->extractTo($extractDir);

        $this->assertFileExists($extractDir . '/file1.txt');
        $this->assertFileDoesNotExist($extractDir . '/file2.log');
        $this->assertDirectoryDoesNotExist($extractDir . '/subdir');
    }

    /**
     * @throws UnsupportedCompressionException
     * @throws DirectoryException
     * @throws FileException
     */
    #[Test]
    public function it_adds_metadata_file(): void
    {
        $metadata = ['version' => '1.0.0', 'build' => '20250717'];
        $options = [
            TarOption::METADATA => $metadata
        ];

        $outputPath = $this->outputDir . DIRECTORY_SEPARATOR . 'archive_with_meta.tar.gz';

        // Act
        $archivePath = tarDirectory($this->sourceDir, CompressionType::GZIP, $outputPath, $options);

        // Assert
        $extractDir = $this->outputDir . DIRECTORY_SEPARATOR . 'extract_meta';
        $phar = new PharData($archivePath);
        $phar->extractTo($extractDir);

        $this->assertFileExists($extractDir . '/.metadata.json');
        $decodedMeta = json_decode(file_get_contents($extractDir . '/.metadata.json'), true);
        $this->assertEquals($metadata, $decodedMeta);

        // Vérifie que les autres fichiers sont toujours là
        $this->assertFileExists($extractDir . '/file1.txt');
    }

    /**
     * @throws UnsupportedCompressionException
     * @throws FileException
     */
    #[Test]
    public function it_throws_exception_if_source_directory_does_not_exist(): void
    {
        $this->expectException(DirectoryException::class);
        tarDirectory('/non/existent/path');
    }

    /**
     * @throws UnsupportedCompressionException
     * @throws DirectoryException
     * @throws FileException
     */
    #[Test]
    public function it_throws_exception_if_no_files_match_filters(): void
    {
        $options = [
            TarOption::EXCLUDE => ['*'] // Exclure tout
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("No files match the filtering criteria.");

        tarDirectory($this->sourceDir, CompressionType::GZIP, null, $options);
    }
}