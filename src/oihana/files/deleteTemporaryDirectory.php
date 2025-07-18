<?php

namespace oihana\files ;

use oihana\enums\Char;
use oihana\files\exceptions\DirectoryException;

/**
 * Deletes a directory located in the system temporary folder (recursively).
 *
 * The given <code>$path</code> is appended to <code>sys_get_temp_dir()</code>
 * in the same way as {@see getTemporaryDirectory()}:
 * - <code>null</code>   → sys temp dir itself,
 * - <code>'logs'</code> → "/tmp/logs",
 * - <code>['my', 'app']</code> → "/tmp/my/app".
 *
 * @param string|string[]|null $path       Optional sub‑path(s) inside sys_get_temp_dir().
 * @param bool                 $assertable Whether to validate the composed directory before deletion. Defaults to true.
 * @param bool                 $isReadable Check readability (passed to {@see assertDirectory()}). Defaults to true.
 * @param bool                 $isWritable Check writability (passed to {@see assertDirectory()}). Defaults to true.
 *
 * @return bool  True if the directory was deleted **or did not exist**.
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

    if ( realpath( $directory ) === realpath( sys_get_temp_dir() ) )
    {
        return false;
    }

    if ( !is_dir( $directory ) )
    {
        return true;
    }

    return deleteDirectory( $directory , $assertable , $isReadable , $isWritable ) ;
}