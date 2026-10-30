<?php

namespace oihana\files ;

use FilesystemIterator;
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
 * **Symbolic links.** By default a link is reproduced as a link, with the target it was written
 * with: a relative link stays relative, an absolute one stays absolute, and the target is never
 * read, so nothing is copied through a link. Pass `$followLinks` to read them instead — a linked
 * directory is then walked and a linked file copied, as if the link were the thing it points at.
 * A link pointing at nothing is skipped either way. Both the exclude patterns and the filter
 * callback see the link's own path, not its target's.
 *
 * @param string        $sourceDir       The path to the source directory to copy from.
 * @param string        $destDir         The path to the destination directory.
 * @param string[]      $excludePatterns An array of patterns to exclude from the copy.
 * @param callable|null $filterCallback  Optional callback for custom filtering. It receives
 * the file path and should return `true` to include it.
 * @param bool          $followLinks     Whether to read symbolic links instead of reproducing
 * them (Default false).
 *
 * @return bool Returns `true` if at least one file or directory was copied, `false` otherwise.
 *
 * @throws DirectoryException If a directory cannot be created in the destination path, if a
 * symbolic link cannot be reproduced, or if `$followLinks` meets a link pointing at a directory
 * that holds it.
 *
 * @security
 * `$excludePatterns` is forwarded to {@see shouldExcludeFile()} which uses
 * `fnmatch()` (glob) or `preg_match()` (regex). **Patterns must come from a
 * trusted source** — a malicious regex can cause CPU exhaustion (ReDoS) since
 * PHP's regex engine has no execution timeout. See the
 * [security guide](../../../wiki/en/security.md#redos-on-user-supplied-regex-patterns).
 *
 * `$followLinks` reads whatever the links in the source point at, which may be anywhere on the
 * disk and outside the source directory. A link pointing at a directory that holds the link itself
 * is refused, since reading it would not end, but two links pointing at each other's branch are
 * not detected — the same caveat as {@see findFiles()}.
 *
 * @example
 *
 * Source directory structure:
 * ```
 * /tmp/source/
 * ├── .git/
 * │   └── config
 * ├── images/
 * │   └── logo.png  (size: 5KB)
 * ├── index.php     (size: 1KB)
 * └── error.log
 * ```
 *
 * ```php
 * $source = '/tmp/source';
 * $destination = '/tmp/destination';
 *
 * // Exclude .git directories and all .log files.
 * $exclude = ['.git', '*.log'];
 *
 * // Only include files smaller than 2KB (2048 bytes).
 * $filter = function(string $filePath)
 * {
 *    return is_dir($filePath) || filesize($filePath) < 2048;
 * };
 *
 * copyFilteredFiles($source, $destination, $exclude, $filter);
 * ```
 *
 * Resulting destination directory:
 * ```
 * /tmp/destination/
 * ├── images/
 * └── index.php
 * ```
 * Explanation:
 * - .git/ was skipped by the exclude pattern.
 * - error.log was skipped by the exclude pattern.
 * - images/logo.png was skipped by the filter callback (size > 2KB).
 * - index.php was copied as it passed both filters.
 *
 * A link, reproduced and then read:
 * ```php
 * symlink( '/var/data/uploads' , '/tmp/source/uploads' ) ;
 *
 * copyFilteredFiles( '/tmp/source' , '/tmp/mirror' ) ;
 * // /tmp/mirror/uploads is a link to /var/data/uploads, nothing was read through it.
 *
 * copyFilteredFiles( '/tmp/source' , '/tmp/backup' , followLinks: true ) ;
 * // /tmp/backup/uploads is a real directory holding a copy of what /var/data/uploads contains.
 * ```
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
function copyFilteredFiles( string $sourceDir , string $destDir , array $excludePatterns = [] , ?callable $filterCallback = null , bool $followLinks = false ): bool
{
    $root   = rtrim( $sourceDir , DIRECTORY_SEPARATOR ) ;
    $offset = strlen( $root ) + 1 ;

    $files = new RecursiveIteratorIterator
    (
        new RecursiveDirectoryIterator( $root , ( $followLinks ? FilesystemIterator::FOLLOW_SYMLINKS : 0 ) | FilesystemIterator::SKIP_DOTS ) ,
        RecursiveIteratorIterator::SELF_FIRST
    );

    $copiedFiles = false ;

    foreach ( $files as $file )
    {
        // The path the walk travelled is what the destination mirrors. Asking getRealPath() here
        // instead resolves a link to its target, outside the source directory, and the relative
        // path cut from it then carries the target's whole absolute path — which is how a copy
        // used to grow a tree of empty directories named after the host's filesystem.

        $pathname = $file->getPathname() ;

        // A link pointing at nothing has nothing to copy, in either mode.

        if ( $file->getRealPath() === false )
        {
            continue ;
        }

        if ( count( $excludePatterns ) > 0 && shouldExcludeFile( $pathname , $excludePatterns ) )
        {
            continue;
        }

        if ( $filterCallback !== null && is_callable( $filterCallback ) )
        {
            if ( !$filterCallback( $pathname ) )
            {
                continue;
            }
        }

        $destPath = $destDir . DIRECTORY_SEPARATOR . substr( $pathname , $offset ) ;

        if ( !$followLinks && $file->isLink() )
        {
            // readlink() gives the target as it was written, relative or absolute, and that is
            // what the copy gets: a link that pointed inside the source still points inside the
            // copy, an absolute one still points where it did.

            $destDirPath = dirname( $destPath ) ;

            if ( !is_dir( $destDirPath ) )
            {
                makeDirectory( $destDirPath ) ;
            }

            if ( !@symlink( readlink( $pathname ) , $destPath ) )
            {
                throw new DirectoryException( sprintf('Failed to copy the symbolic link "%s" to "%s".' , $pathname , $destPath ) ) ;
            }
        }
        else if ( $file->isDir() )
        {
            // Reading a link that points at a directory holding the link itself means walking
            // that directory again, inside itself, and again, without end. Comparing the target
            // against the source root alone would miss a link to its own parent further down.

            if ( $followLinks && $file->isLink() )
            {
                $parentRealPath = realpath( dirname( $pathname ) ) ;
                $targetRealPath = $file->getRealPath() ;

                if ( $parentRealPath !== false
                     && str_starts_with( $parentRealPath . DIRECTORY_SEPARATOR , $targetRealPath . DIRECTORY_SEPARATOR ) )
                {
                    throw new DirectoryException( sprintf
                    (
                        'The symbolic link "%s" points at "%s", which holds it: reading it would not end.' ,
                        $pathname ,
                        $targetRealPath
                    ) ) ;
                }
            }

            makeDirectory( $destPath ) ;
        }
        else
        {
            $destDirPath = dirname( $destPath ) ;

            if ( !is_dir( $destDirPath ) )
            {
                makeDirectory( $destDirPath );
            }

            copy( $pathname , $destPath ) ;
        }

        $copiedFiles = true;
    }

    return $copiedFiles;
}
