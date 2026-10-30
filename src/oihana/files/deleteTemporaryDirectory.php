<?php

namespace oihana\files ;

use oihana\enums\Char;
use oihana\files\exceptions\DirectoryException;

use function oihana\files\path\canonicalizePath;
use function oihana\files\path\isBasePath;

/**
 * Deletes a directory located in the system temporary folder (recursively).
 *
 * The given <code>$path</code> is appended to <code>sys_get_temp_dir()</code>
 * in the same way as {@see getTemporaryDirectory()}:
 * - <code>null</code>   → sys temp dir itself,
 * - <code>'logs'</code> → "/tmp/logs",
 * - <code>['my', 'app']</code> → "/tmp/my/app".
 *
 * **Nothing outside the temporary directory is deleted.** {@see getTemporaryDirectory()} hands back
 * an absolute path as it stands, and joins a relative one without resolving it, so a path may well
 * come out of it pointing anywhere on the disk — `'/etc'`, or a `'../../..'` climb. Such a path is
 * refused, with `false`, as is the temporary directory itself. The check is made on what the
 * filesystem resolves rather than on the string: the parent is resolved, so a linked ancestor
 * cannot be used to reach out, while the last segment is left as it stands, so a symbolic link
 * sitting in the temporary directory is removed on its own — never followed, its target kept.
 *
 * @param string|string[]|null $path       Optional sub‑path(s) inside sys_get_temp_dir().
 * @param bool                 $assertable Whether to validate the composed directory before deletion. Defaults to true.
 * @param bool                 $isReadable Check readability (passed to {@see assertDirectory()}). Defaults to true.
 * @param bool                 $isWritable Check writability (passed to {@see assertDirectory()}). Defaults to true.
 *
 * @return bool  True if the directory was deleted **or did not exist**. False when the path is
 *                empty, is the temporary directory itself, or lies outside it.
 *
 * @throws DirectoryException If validation/deletion fails.
 *
 * @example
 * ```php
 * use function oihana\files\deleteTemporaryDirectory;
 *
 * // Remove /tmp/old_reports (et son contenu)
 * deleteTemporaryDirectory('old_reports');
 *
 * // Remove /tmp/tmp123/cache/images
 * deleteTemporaryDirectory(['tmp123', 'cache', 'images']);
 *
 * // Force failure if folder is not writable
 * deleteTemporaryDirectory('protected_dir', isWritable: true);
 * ```
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
function deleteTemporaryDirectory( string|array|null $path , bool $assertable = true , bool $isReadable = true , bool $isWritable = true ): bool
{
    $directory = getTemporaryDirectory( $path ) ;

    if ( $directory === Char::EMPTY )
    {
        return false;
    }

    $base = realpath( sys_get_temp_dir() ) ;

    // The parent is resolved and the last segment is not: a linked ancestor cannot be used to
    // reach outside, and a link sitting in the temporary directory is still judged by where it
    // stands rather than by what it points at. When the parent does not exist there is nothing
    // to delete, and the lexical path is enough to answer.

    $parent   = realpath( dirname( $directory ) ) ;
    $resolved = $parent !== false
              ? $parent . DIRECTORY_SEPARATOR . basename( $directory )
              : canonicalizePath( $directory ) ;

    if ( $base === false || $resolved === $base )
    {
        return false;
    }

    if ( !isBasePath( $base , $resolved ) )
    {
        return false;
    }

    if ( !is_dir( $directory ) )
    {
        return true;
    }

    return deleteDirectory( $directory , $assertable , $isReadable , $isWritable ) ;
}