<?php

namespace oihana\files ;

use oihana\enums\Char;
use oihana\files\exceptions\DirectoryException;

/**
 * Normalises (and optionally validates) a directory path.
 *
 * - If <code>$path</code> is an array, empty segments and <code>Char::EMPTY</code> are removed, then the remaining parts are joined with <code>DIRECTORY_SEPARATOR</code>.
 * - If <code>$assertable</code> is true (default), {@see assertDirectory()} ensures the resulting path exists and is readable.
 * - Trailing separators are always stripped before return.
 *
 * @param string|array|null $path Directory or segments to normalise. <br>
 * Examples: <code>'/tmp'</code> or <code>['tmp','logs']</code>.
 * @param bool $assertable Whether to validate the resulting path. Default: true.
 * @param bool $isReadable Whether to assert that the directory is readable. Default: true.
 * @param bool $isWritable Whether to assert that the directory is writable. Default: false.
 *
 * @return string Normalized directory path.
 *
 * @throws DirectoryException If validation is enabled and the directory is invalid.
 *
 * @example
 * **Basic use with a character string**
 * Validates that the system temporary directory exists and deletes the final separator.
 * ```php
 * $path = getDirectory( sys_get_temp_dir() . DIRECTORY_SEPARATOR );
 * // $path contient maintenant quelque chose comme '/tmp' ou 'C:\Users\...\Temp'.
 * ```
 *
 * **Builds and validates a path from an array.**
 * Empty or null elements are ignored (assumes ‘/tmp/logs’ exists and is readable).
 * ```php
 * $parts = [sys_get_temp_dir(), '', 'logs', null];
 * $path = getDirectory($parts);
 * // $path contient maintenant quelque chose comme '/tmp/logs'.
 * ```
 *
 * **Normalizes a path without validating it**
 * Ne lève pas d'exception si le chemin n'existe pas.
 * ```php
 * $path = getDirectory('/path/not/exist/', assertable: false);
 * // $path contains '/path/not/exist/'.
 * ```
 *
 * **Validates that a directory is also writable.**
 * ```php
 * try
 * {
 *     $path = getDirectory(sys_get_temp_dir(), isWritable: true);
 *     // The script continue if the directory is writable
 * }
 * catch ( DirectoryException $e )
 * {
 *     // Thrown an error
 * }
 * ```
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
function getDirectory( string|array|null $path , bool $assertable = true , bool $isReadable = true , bool $isWritable = false ): string
{
    if ( is_array( $path ) )
    {
        $path = array_filter
        (
            $path ,
            static fn( ?string $p ): bool => is_string( $p ) && $p !== Char::EMPTY
        );
        $path = implode(DIRECTORY_SEPARATOR , $path ) ;
    }

    $path = $path ?? Char::EMPTY;

    if( $assertable )
    {
        assertDirectory( $path , isReadable : $isReadable , isWritable : $isWritable ) ;
    }

    return rtrim( $path , DIRECTORY_SEPARATOR ) ;
}