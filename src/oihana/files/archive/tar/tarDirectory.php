<?php

namespace oihana\files\archive\tar;

use oihana\files\enums\CompressionType;
use oihana\files\enums\TarExtension;
use oihana\files\enums\TarOption;
use oihana\files\exceptions\DirectoryException;

use oihana\files\exceptions\FileException;
use oihana\files\exceptions\UnsupportedCompressionException;

use RuntimeException;

use function oihana\reflect\helpers\getFunctionInfo;
use function oihana\files\assertDirectory;
use function oihana\files\copyFilteredFiles;
use function oihana\files\deleteDirectory;
use function oihana\files\makeDirectory;

/**
 * Creates a tar archive from a directory with specified compression.
 *
 * This function creates a compressed (or uncompressed) tar archive from
 * the given directory. It supports filtering files by exclude patterns,
 * by a callback filter function, and adding optional metadata saved as
 * `.metadata.json` inside the archive.
 *
 * If no filters or metadata are provided, it simply creates the archive
 * directly from the directory. Otherwise, it copies filtered files to
 * a temporary directory and archives from there.
 *
 * @param string           $directory    The source directory to archive.
 * @param ?string          $compression  Compression type (e.g. gzip, bzip2, none).
 *                                      Default is gzip compression.
 * @param string|null      $outputPath   Optional output archive path.
 *                                      If null, defaults to directory name plus
 *                                      extension based on compression.
 * @param array            $options      Additional options:
 *                                        - **exclude**  => string[] list of glob patterns or file names to exclude
 *                                        - **filter**   => callable|null a function (string $filepath): bool
 *                                        - **metadata** => array<string, string> extra metadata to embed in `.metadata.json`
 *
 * @return string Returns the full path to the created archive file.
 *
 * @throws DirectoryException If the source directory does not exist or is inaccessible.
 * @throws FileException If there are issues writing files or archives.
 * @throws UnsupportedCompressionException If an unsupported compression type is specified.
 * @throws RuntimeException If no files match filtering criteria.
 *
 * @example
 * ```php
 * // Create a gzip compressed tar archive from directory /var/www/html
 * $archive = tarDirectory('/var/www/html');
 * echo $archive; // /var/www/html.tar.gz
 *
 * // Create a bz2 compressed archive, excluding .git and node_modules folders
 * $archive = tarDirectory(
 *     '/var/www/html',
 *     CompressionType::BZIP2,
 *     null,
 *     [
 *         TarOption::EXCLUDE => ['.git', 'node_modules'],
 *     ]
 * );
 *
 * // Create an archive with a custom filter callback and add metadata
 * $archive = tarDirectory(
 *     '/var/www/html',
 *     CompressionType::NONE,
 *     '/backups/html_backup.tar',
 *     [
 *         TarOption::FILTER => function(string $filePath): bool {
 *             // Only include PHP files
 *             return str_ends_with($filePath, '.php');
 *         },
 *         TarOption::METADATA => [
 *             'createdBy' => 'admin',
 *             'description' => 'Backup of PHP source files',
 *         ],
 *     ]
 * );
 * ```
 *
 * @package oihana\files\archive\tar
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
function tarDirectory
(
    string $directory ,
    ?string $compression = CompressionType::GZIP ,
    ?string $outputPath  = null ,
    array $options       = []
): string
{
    assertDirectory( $directory ) ;


    if ( $outputPath === null )
    {
        $archiveName = basename( $directory ) ;
        $extension   = TarExtension::getExtensionForCompression( $compression );
        $outputPath  = dirname( $directory ) . DIRECTORY_SEPARATOR . $archiveName . $extension;
    }

    $excludePatterns = $options[ TarOption::EXCLUDE  ] ?? [] ;
    $filterCallback  = $options[ TarOption::FILTER   ] ?? null ;
    $metadata        = $options[ TarOption::METADATA ] ?? [] ;

    if ( empty( $excludePatterns ) && $filterCallback === null && empty( $metadata ) )
    {
        return tar( $directory , $outputPath , $compression , $directory ) ;
    }

    $tmpPath = getFunctionInfo('oihana\files\archive\tar\tarDirectory' )[ 'name' ] ;
    $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . str_replace( "\\", DIRECTORY_SEPARATOR , $tmpPath ) . DIRECTORY_SEPARATOR ;
    $tempDir = $tmpPath . uniqid() ;

    makeDirectory( $tempDir ) ;

    try
    {
        $copiedFiles = copyFilteredFiles($directory, $tempDir, $excludePatterns, $filterCallback);

        if ( !empty( $metadata ) )
        {
            $metaJson = json_encode( $metadata , JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ;
            file_put_contents($tempDir . DIRECTORY_SEPARATOR . '.metadata.json' , $metaJson ) ;
            $copiedFiles = true;
        }

        if ( !$copiedFiles )
        {
            throw new RuntimeException("No files match the filtering criteria." ) ;
        }

        return tar( $tempDir , $outputPath , $compression , $tempDir );
    }
    finally
    {
       deleteDirectory( $tempDir ) ;
    }
}
