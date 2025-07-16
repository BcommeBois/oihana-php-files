<?php

namespace oihana\files\archive;

use Exception;
use FilesystemIterator;
use oihana\enums\Char;
use oihana\files\enums\CompressionType;
use oihana\files\enums\FileExtension;
use oihana\files\enums\TarOption;
use oihana\files\exceptions\DirectoryException;
use oihana\files\exceptions\FileException;

use PharData;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

use function oihana\files\assertDirectory;
use function oihana\files\getPharCompressionType;

/**
 * Manages creating and extracting Unix tar (optionally gz‑compressed) archives.
 * If that component is not available you can switch to the fallback methods that
 */
final class TarFile
{
    /**
     * Supported tar‑related extensions (lower‑case, with leading dot).
     * @var string[]
     */
    public const array TAR_EXTENSIONS = [ FileExtension::GZ , FileExtension::TAR , FileExtension::TGZ , FileExtension::TAR_BZ2 , FileExtension::TAR_GZ ] ;

    /**
     * Creates a tar archive from one or more files and/or directories.
     *
     * @param string|string[] $paths Absolute paths to files or directories to include in the archive.
     * @param string|null $outputPath Optional final output path of the archive. If null, an automatic name is generated.
     * @param string|null $compression Compression type to use (Default : CompressionType::GZIP).
     * @param string|null $preserveRoot If set, all paths in the archive will be stored relative to this directory.
     *                                  Useful to preserve directory structure.
     *
     * @return string The path to the generated archive.
     *
     * @throws FileException If a path does not exist or is invalid.
     * @throws RuntimeException On any failure during archive creation.
     *
     * @see CompressionType
     */
    public function tar
    (
        string|array $paths ,
        ?string      $outputPath   = null ,
        ?string      $compression  = CompressionType::GZIP ,
        ?string      $preserveRoot = null
    )
    :string
    {
        if ( is_string( $paths ) )
        {
            $paths = [ $paths ] ;
        }

        if ( empty( $paths ) )
        {
            throw new RuntimeException("No input paths provided.");
        }

        foreach ( $paths as $path )
        {
            if ( !file_exists( $path ) )
            {
                throw new FileException( sprintf( "The path does not exist: ‰s" , $path ) ) ;
            }
        }

        $archiveName = $outputPath !== null
            ? pathinfo( $outputPath , PATHINFO_FILENAME )
            : 'archive_' . date('Ymd_His');

        $baseTarPath = $compression === CompressionType::NONE
            ? ( $outputPath ?? ( sys_get_temp_dir() . DIRECTORY_SEPARATOR . $archiveName . FileExtension::TAR ) )
            : sys_get_temp_dir() . DIRECTORY_SEPARATOR . $archiveName . FileExtension::TAR ;

        try
        {
            $phar = new PharData( $baseTarPath );

            foreach ( $paths  as $path )
            {
                $realPath = realpath( $path );
                if ( $realPath === false )
                {
                    throw new FileException( sprintf("The path is invalid: ‰s" , $path ) );
                }

                $archivePath = $preserveRoot !== null
                    ? ltrim( str_replace( realpath( $preserveRoot ) , '' , $realPath ) , DIRECTORY_SEPARATOR )
                    : basename( $realPath ) ;

                if ( is_dir( $realPath ) )
                {
                    $files = new RecursiveIteratorIterator
                    (
                        new RecursiveDirectoryIterator( $realPath , FilesystemIterator::SKIP_DOTS ) ,
                        RecursiveIteratorIterator::SELF_FIRST
                    ) ;

                    foreach ( $files as $file )
                    {
                        $filePath = $file->getRealPath() ;
                        if ( $filePath === false )
                        {
                            continue;
                        }

                        $relativePath = $preserveRoot !== null
                            ? ltrim( str_replace( realpath($preserveRoot) , '' , $filePath ) , DIRECTORY_SEPARATOR )
                            : ltrim( str_replace( $realPath , $archivePath , $filePath ) , DIRECTORY_SEPARATOR ) ;

                        if ( $file->isDir() )
                        {
                            $phar->addEmptyDir( $relativePath ) ;
                        }
                        else
                        {
                            $phar->addFile( $filePath , $relativePath ) ;
                        }
                    }
                }
                else
                {
                    $phar->addFile( $realPath , $archivePath ) ;
                }
            }

            if ( $compression !== CompressionType::NONE )
            {
                $phar->compress( getPharCompressionType( $compression ) ) ;

                unset( $phar ) ;

                $compressedPath = $baseTarPath . $this->getCompressionExtension( $compression );

                if ( $outputPath !== null && $compressedPath !== $outputPath )
                {
                    rename( $compressedPath , $outputPath ) ;
                }

                if ( file_exists( $baseTarPath ) )
                {
                    unlink( $baseTarPath );
                }

                return $outputPath ?? $compressedPath;
            }

            unset( $phar ) ;

            return $baseTarPath ;
        }
        catch ( Exception $exception )
        {
            throw new RuntimeException("Failed to create archive from paths. Error: " . $exception->getMessage() , 0 , $exception );
        }
    }

    /**
     * Creates a tar archive from a directory with specified compression.
     *
     * @param string $directory The source directory to archive.
     * @param ?string $compression Compression type (e.g. gzip, bzip2, none).
     * @param string|null $outputPath Optional custom output path. If null, uses directory name with appropriate extension.
     * @param array $options Additional options:
     *      - 'exclude'  => string[] list of glob patterns or file names to exclude
     *      - 'filter'   => callable|null a function (string $filepath): bool
     *      - 'metadata' => array<string, string> additional metadata to include in `.metadata.json`
     *
     * @return string The path to the created archive.
     *
     * @throws DirectoryException If the directory does not exist.
     * @throws RuntimeException If the archive creation fails.
     */
    public function tarDirectory
    (
        string $directory ,
        ?string $compression = CompressionType::GZIP ,
        ?string $outputPath  = null ,
        array $options     = []
    ): string
    {
        assertDirectory( $directory ) ;

        $archiveName = basename( $directory ) ;

        if ( $outputPath === null )
        {
            $extension  = $this->getExtensionForCompression( $compression ) ;
            $outputPath = $directory . $extension ;
        }

        $excludePatterns = $options[ TarOption::EXCLUDE  ] ?? [] ;
        $filterCallback  = $options[ TarOption::FILTER   ] ?? null ;
        $metadata        = $options[ TarOption::METADATA ] ?? [] ;

        try
        {
            $baseTarPath = $compression === CompressionType::NONE
                ? $outputPath
                : sys_get_temp_dir() . DIRECTORY_SEPARATOR . $archiveName . FileExtension::TAR ;

            $phar = new PharData( $baseTarPath ) ;
            $phar->buildFromDirectory( $directory ) ;


            $files = new RecursiveIteratorIterator
            (
                new RecursiveDirectoryIterator( $directory , FilesystemIterator::SKIP_DOTS ) ,
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ( $files as $file )
            {
                $filePath = $file->getRealPath() ;
                if ( $filePath === false )
                {
                    continue;
                }

                // Apply exclude patterns
                $excluded = false;
                foreach ( $excludePatterns as $pattern )
                {
                    if ( fnmatch( $pattern , basename( $filePath ) ) )
                    {
                        $excluded = true;
                        break;
                    }
                }

                if ( $excluded )
                {
                    continue;
                }

                // Apply custom filter callback
                if ( $filterCallback !== null && is_callable( $filterCallback ) )
                {
                    if ( !$filterCallback( $filePath ) )
                    {
                        continue;
                    }
                }

                $relativePath = ltrim(str_replace(realpath($directory), '', $filePath), DIRECTORY_SEPARATOR);

                if ( $file->isDir())
                {
                    $phar->addEmptyDir( $relativePath ) ;
                }
                else
                {
                    $phar->addFile( $filePath , $relativePath ) ;
                }
            }

            // Add metadata file if provided
            if ( !empty( $metadata ) )
            {
                $metaJson     = json_encode( $metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ;
                $metaTempFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . '.metadata.json';
                file_put_contents( $metaTempFile , $metaJson ) ;
                $phar->addFile( $metaTempFile , '.metadata.json' ) ;
            }

            // Apply compression if requested
            if ( $compression !== CompressionType::NONE )
            {
                $phar->compress( getPharCompressionType( $compression ) ) ;

                unset( $phar ) ;

                $compressedPath = $baseTarPath . $this->getCompressionExtension( $compression ) ;
                if ( $compressedPath !== $outputPath )
                {
                    rename( $compressedPath , $outputPath ) ;
                }

                if ( file_exists( $baseTarPath ) )
                {
                    unlink( $baseTarPath ) ;
                }
            }
            else
            {
                unset( $phar ) ;
            }

            return $outputPath;

        }
        catch ( Exception $e )
        {
            throw new RuntimeException( sprintf( 'Failed to create tar archive from directory %s. Error: %s' , $directory , $e->getMessage() ) ) ;
        }
    }

    /**
     * Gets the appropriate file extension for a compression type.
     * @param string $compression The compression type.
     * @return string The file extension.
     */
    private function getExtensionForCompression( string $compression ): string
    {
        return match ( $compression )
        {
            CompressionType::GZIP  => FileExtension::TAR_GZ   ,
            CompressionType::BZIP2 => FileExtension::TAR_BZ2  ,
            default                => FileExtension::TAR      , // NONE
        } ;
    }

    /**
     * Gets the file extension added by Phar compression.
     * @param string $compression The compression type.
     * @return string The extension added by compression.
     */
    private function getCompressionExtension( string $compression ): string
    {
        return match ( $compression )
        {
            CompressionType::GZIP  => FileExtension::GZ  ,
            CompressionType::BZIP2 => FileExtension::BZ2 ,
            CompressionType::NONE  => Char::EMPTY ,
        } ;
    }
}
