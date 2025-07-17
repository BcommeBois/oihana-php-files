<?php

namespace oihana\files\archive\tar;

use Exception;
use FilesystemIterator;
use oihana\files\enums\CompressionType;
use oihana\files\enums\FileExtension;
use oihana\files\enums\TarExtension;
use oihana\files\enums\TarOption;
use oihana\files\exceptions\DirectoryException;

use PharData;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

use function oihana\files\assertDirectory;
use function oihana\files\getPharCompressionType;

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
function tarDirectory
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
        $extension  = TarExtension::getExtensionForCompression( $compression ) ;
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
            $metaJson = json_encode( $metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ;
            $metaTempFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . '.metadata.json';
            file_put_contents( $metaTempFile , $metaJson ) ;
            $phar->addFile( $metaTempFile , '.metadata.json' ) ;
        }

        // Apply compression if requested
        if ( $compression !== CompressionType::NONE )
        {
            $phar->compress( getPharCompressionType( $compression ) ) ;

            unset( $phar ) ;

            $compressedPath = $baseTarPath . TarExtension::getCompressionExtension( $compression ) ;
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
