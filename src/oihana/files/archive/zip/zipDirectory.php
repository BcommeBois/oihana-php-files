<?php

namespace oihana\files\archive\zip;

use RuntimeException;

use oihana\files\enums\CompressionType;
use oihana\files\enums\FileExtension;
use oihana\files\enums\ZipOption;
use oihana\files\exceptions\DirectoryException;
use oihana\files\exceptions\FileException;
use oihana\files\exceptions\UnsupportedCompressionException;

use function oihana\reflect\helpers\getFunctionInfo;
use function oihana\files\assertDirectory;
use function oihana\files\copyFilteredFilesWithMetadata;
use function oihana\files\deleteDirectory;
use function oihana\files\makeDirectory;

/**
 * Creates a zip archive from a directory.
 *
 * This function creates a zip archive from the given directory. It supports filtering
 * files by exclude patterns, by a callback filter function, and adding optional metadata
 * saved as `.metadata.json` inside the archive.
 *
 * If no filters or metadata are provided, it simply creates the archive directly from the
 * directory (preserving its root). Otherwise, it copies the filtered files to a temporary
 * directory and archives from there.
 *
 * @param string      $directory   The source directory to archive.
 * @param ?string     $compression Per-entry compression method ({@see CompressionType::ZIP}
 *                                 — DEFLATE, default — or {@see CompressionType::NONE} — stored).
 * @param string|null $outputPath  Optional output archive path. If null, defaults to the
 *                                 directory name plus the `.zip` extension.
 * @param array       $options     Additional options:
 *                                  - **exclude**  => string[] list of glob patterns or file names to exclude
 *                                  - **filter**   => callable|null a function (string $filepath): bool
 *                                  - **metadata** => array<string, string> extra metadata to embed in `.metadata.json`
 *
 * @return string Returns the full path to the created archive file.
 *
 * @throws DirectoryException If the source directory does not exist or is inaccessible.
 * @throws FileException If there are issues writing files or archives.
 * @throws UnsupportedCompressionException If an unsupported compression method is specified.
 * @throws RuntimeException If no files match the filtering criteria.
 *
 * @example
 * ```php
 * // Create a zip archive from directory /var/www/html
 * $archive = zipDirectory('/var/www/html');
 * echo $archive; // /var/www/html.zip
 *
 * // Create a stored (uncompressed) archive, excluding .git and node_modules
 * $archive = zipDirectory(
 *     '/var/www/html',
 *     CompressionType::NONE,
 *     null,
 *     [ ZipOption::EXCLUDE => ['.git', 'node_modules'] ]
 * );
 *
 * // Create an archive with a custom filter callback and embedded metadata
 * $archive = zipDirectory(
 *     '/var/www/html',
 *     CompressionType::ZIP,
 *     '/backups/html_backup.zip',
 *     [
 *         ZipOption::FILTER   => fn( string $filePath ): bool => str_ends_with( $filePath , '.php' ),
 *         ZipOption::METADATA => [ 'createdBy' => 'admin' , 'description' => 'PHP source backup' ],
 *     ]
 * );
 * ```
 *
 * @package oihana\files\archive\zip
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.2.0
 */
function zipDirectory
(
    string $directory ,
    ?string $compression = CompressionType::ZIP ,
    ?string $outputPath  = null ,
    array $options       = []
): string
{
    assertDirectory( $directory ) ;

    if ( $outputPath === null )
    {
        $archiveName = basename( $directory ) ;
        $outputPath  = dirname( $directory ) . DIRECTORY_SEPARATOR . $archiveName . FileExtension::ZIP ;
    }

    $excludePatterns = $options[ ZipOption::EXCLUDE  ] ?? [] ;
    $filterCallback  = $options[ ZipOption::FILTER   ] ?? null ;
    $metadata        = $options[ ZipOption::METADATA ] ?? [] ;

    if ( empty( $excludePatterns ) && $filterCallback === null && empty( $metadata ) )
    {
        return zip( $directory , $outputPath , $compression , $directory ) ;
    }

    $tmpPath = getFunctionInfo('oihana\files\archive\zip\zipDirectory' )[ 'name' ] ;
    $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . str_replace( "\\" , DIRECTORY_SEPARATOR , $tmpPath ) . DIRECTORY_SEPARATOR ;
    $tempDir = $tmpPath . uniqid() ;

    makeDirectory( $tempDir ) ;

    try
    {
        copyFilteredFilesWithMetadata( $directory , $tempDir , $excludePatterns , $filterCallback , $metadata ) ;
        return zip( $tempDir , $outputPath , $compression , $tempDir ) ;
    }
    finally
    {
        deleteDirectory( $tempDir ) ;
    }
}
