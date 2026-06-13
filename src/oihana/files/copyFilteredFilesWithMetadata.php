<?php

namespace oihana\files ;

use oihana\files\exceptions\DirectoryException;
use RuntimeException;

/**
 * Copies the filtered files of a directory into a destination directory and, optionally,
 * embeds a `.metadata.json` file describing the archive.
 *
 * This is the staging step shared by the directory-archiving helpers
 * ({@see \oihana\files\archive\tar\tarDirectory()} and
 * {@see \oihana\files\archive\zip\zipDirectory()}): it delegates the copy to
 * {@see copyFilteredFiles()}, writes the metadata when provided, and guarantees the destination is non-empty.
 *
 * Embedding metadata always makes the destination non-empty, even when no source file
 * matched the filters (the archive then contains only `.metadata.json`).
 *
 * @param string $sourceDir The source directory to copy from.
 * @param string $destDir The destination (staging) directory to copy into.
 * @param string[] $excludePatterns Glob patterns or file names to exclude.
 * @param callable|null $filterCallback Optional `function (string $filepath): bool` returning
 *                                       `true` to include the item.
 * @param array<string,mixed> $metadata Optional metadata embedded as `.metadata.json`.
 *
 * @return void
 *
 * @throws DirectoryException
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.2.0
 *
 * @example
 * ```php
 * use function oihana\files\copyFilteredFilesWithMetadata;
 *
 * copyFilteredFilesWithMetadata(
 *     '/var/www/html',
 *     '/tmp/staging',
 *     ['.git', 'node_modules'],
 *     fn( string $path ): bool => str_ends_with( $path , '.php' ),
 *     [ 'createdBy' => 'admin' ]
 * );
 * ```
 */
function copyFilteredFilesWithMetadata
(
    string $sourceDir ,
    string $destDir ,
    array $excludePatterns = [] ,
    ?callable $filterCallback = null ,
    array $metadata = []
): void
{
    $copiedFiles = copyFilteredFiles( $sourceDir , $destDir , $excludePatterns , $filterCallback ) ;

    if ( !empty( $metadata ) )
    {
        $metaJson = json_encode( $metadata , JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ;
        file_put_contents( $destDir . DIRECTORY_SEPARATOR . '.metadata.json' , $metaJson ) ;
        $copiedFiles = true ;
    }

    if ( !$copiedFiles )
    {
        throw new RuntimeException("No files match the filtering criteria." ) ;
    }
}
