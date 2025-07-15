<?php

namespace oihana\files\path ;

use oihana\enums\Char;

/**
 * Normalizes the given file system path by replacing backslashes with slashes.
 *
 * This function is useful to unify path separators across different operating systems.
 * It ensures all directory separators are forward slashes (`/`), making the path
 * suitable for consistent comparison, storage, or manipulation.
 *
 * It does **not** resolve relative components like `.` or `..`, nor does it
 * canonicalize or validate the path against the filesystem.
 *
 * @param string $path The path to normalize. Can contain mixed or inconsistent separators.
 *
 * @return string The normalized path with all backslashes (`\`) replaced by forward slashes (`/`).
 *
 * @example
 * ```php
 * normalizePath('C:\\Users\\myuser\\Documents');
 * // Returns: 'C:/Users/myuser/Documents'
 *
 * normalizePath('/var/www/html');
 * // Returns: '/var/www/html'
 * ```
 *
 * @see realpath() For actual filesystem canonicalization.
 */
function normalizePath( string $path ) :string
{
    return str_replace( Char::BACK_SLASH , Char::SLASH , $path ) ;
}