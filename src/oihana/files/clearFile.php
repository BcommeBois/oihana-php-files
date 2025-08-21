<?php

namespace oihana\files ;

use oihana\files\exceptions\FileException;

/**
 * Clears the content of a file while keeping the file itself.
 *
 * This function empties the given file. Returns true if the file was successfully cleared,
 * false otherwise.
 *
 * The behavior on failure depends on the `$assertable` parameter:
 * - If `$assertable` is true (default), a FileException is thrown if the file does not exist
 * or is not writable.
 * - If `$assertable` is false, no exception is thrown; the function simply returns false on failure.
 *
 * @param string|null $file       The full path to the file to clear.
 * @param bool        $assertable Whether to throw exceptions on failure (default: true).
 *
 * @return bool True if the file was cleared successfully, false otherwise.
 *
 * @throws FileException If `$assertable` is true and the file does not exist or is not writable.
 *
 * @example
 * ```php
 * use function oihana\files\clearFile;
 *
 * $file = '/path/to/file.txt';
 *
 * // Clear the file, throwing exception on failure
 * $success = clearFile($file);
 *
 * // Clear the file, returning false instead of throwing an exception
 * $success = clearFile($file, assertable: false);
 * ```
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
function clearFile( ?string $file , bool $assertable = true ) : bool
{
    if( $assertable )
    {
        assertFile( $file , isWritable: true ) ;
    }
    else if ($file === null || !is_writable($file))
    {
        return false;
    }

    return file_put_contents( $file , '' ) !== false ;
}