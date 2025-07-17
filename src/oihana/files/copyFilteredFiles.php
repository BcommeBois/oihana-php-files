<?php

namespace oihana\files ;

use FilesystemIterator;
use oihana\enums\Char;
use oihana\files\exceptions\DirectoryException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Recursively copies files and directories from a source to a destination with filtering.
 *
 * This function iterates through a source directory and copies its contents to a
 * destination directory, preserving the folder structure. It provides two methods
 * for filtering which files and directories get copied:
 *
 * 1. `$excludePatterns`: An array of glob/regex patterns. Any file or directory
 * matching a pattern in this array will be skipped. See `shouldExcludeFile()`.
 * 2. `$filterCallback`: An optional user-defined function. This callback receives the
 * full path of each item and must return `true` for the item to be copied.
 *
 * Destination directories are created as needed.
 *
 * @param string        $sourceDir       The path to the source directory to copy from.
 * @param string        $destDir         The path to the destination directory.
 * @param string[]      $excludePatterns An array of patterns to exclude from the copy.
 * @param callable|null $filterCallback  Optional callback for custom filtering. It receives
 * the file path and should return `true` to include it.
 *
 * @return bool Returns `true` if at least one file or directory was copied, `false` otherwise.
 *
 * @throws DirectoryException If a directory cannot be created in the destination path.
 *
 * @example
 * ```
 * // Source directory structure:
 * // /tmp/source/
 * // ├── .git/
 * // │   └── config
 * // ├── images/
 * // │   └── logo.png  (size: 5KB)
 * // ├── index.php     (size: 1KB)
 * // └── error.log
 *
 * $source = '/tmp/source';
 * $destination = '/tmp/destination';
 *
 * // Exclude .git directories and all .log files.
 * $exclude = ['.git', '*.log'];
 *
 * // Only include files smaller than 2KB (2048 bytes).
 * $filter = function(string $filePath) {
 * return is_dir($filePath) || filesize($filePath) < 2048;
 * };
 *
 * copyFilteredFiles($source, $destination, $exclude, $filter);
 *
 * // Resulting destination directory:
 * // /tmp/destination/
 * // ├── images/
 * // └── index.php
 *
 * // Explanation:
 * // - .git/ was skipped by the exclude pattern.
 * // - error.log was skipped by the exclude pattern.
 * // - images/logo.png was skipped by the filter callback (size > 2KB).
 * // - index.php was copied as it passed both filters.
 * ```
 */
function copyFilteredFiles( string $sourceDir , string $destDir , array $excludePatterns = [] , ?callable $filterCallback = null ): bool
{
    $files = new RecursiveIteratorIterator
    (
        new RecursiveDirectoryIterator( $sourceDir, FilesystemIterator::SKIP_DOTS ) ,
        RecursiveIteratorIterator::SELF_FIRST
    );

    $copiedFiles = false ;

    foreach ( $files as $file )
    {
        $filePath = $file->getRealPath();
        if ( $filePath === false )
        {
            continue ;
        }

        if ( count( $excludePatterns ) > 0 && shouldExcludeFile( $filePath , $excludePatterns ) )
        {
            continue;
        }

        if ( $filterCallback !== null && is_callable( $filterCallback ) )
        {
            if ( !$filterCallback( $filePath ) )
            {
                continue;
            }
        }

        $relativePath = ltrim( str_replace( realpath( $sourceDir ) , Char::EMPTY , $filePath ) , DIRECTORY_SEPARATOR ) ;
        $destPath     = $destDir . DIRECTORY_SEPARATOR . $relativePath ;

        if ( $file->isDir() )
        {
            makeDirectory( $destPath ) ;
        }
        else
        {
            $destDirPath = dirname( $destPath ) ;

            if ( !is_dir( $destDirPath ) )
            {
                makeDirectory( $destDirPath );
            }

            copy( $filePath , $destPath ) ;
        }

        $copiedFiles = true;
    }

    return $copiedFiles;
}