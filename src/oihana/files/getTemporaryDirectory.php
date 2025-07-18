<?php

namespace oihana\files ;

use oihana\enums\Char;
use oihana\files\exceptions\DirectoryException;

/**
 * Builds a path inside the system temporary directory.
 *
 * @param string|string[]|null $path Optional sub‑path(s) to append inside sys_get_temp_dir().
 * @param bool $assertable Whether to validate the final directory path. Defaults to false because the directory may not exist yet.
 * @param bool $isReadable Check if the directory is readable (Default true).
 * @param bool $isWritable Check if the directory is writable (Default false).
 *
 * @return string Normalised temporary directory path.
 *
 * @throws DirectoryException If validation is enabled and the path is invalid.
 *
 * @example
 *
 * Basic usage: get the system temp directory.
 * ```php
 * use function oihana\files\getTemporaryDirectory;
 *
 * echo getTemporaryDirectory();
 * // e.g. "/tmp" on Unix, "C:\Windows\Temp" on Windows
 * ```
 *
 * Append a subdirectory path:
 * ```php
 * echo getTemporaryDirectory('myapp/cache'); // e.g. "/tmp/myapp/cache"
 * echo getTemporaryDirectory(['myapp', 'logs']); // e.g. "/tmp/myapp/logs"
 * ```
 *
 * Validate that the directory exists and is readable:
 * ```php
 * try
 * {
 *     $dir = getTemporaryDirectory('myapp/logs', true); // assertable = true
 *     echo $dir;
 * }
 * catch ( DirectoryException $e )
 * {
 *     // Handle error if directory does not exist or is not readable
 * }
 * ```
 *
 * Validate that the directory is writable:
 * ```php
 * try
 * {
 *    // assertable + readable + writable
 *    $dir = getTemporaryDirectory('myapp/uploads', true, true, true);
 *    echo $dir;
 * }
 * catch (DirectoryException $e)
 * {
 *    // Handle permission error
 * }
 * ```
 *
 * Using an absolute path (bypasses sys_get_temp_dir):
 * ```php
 * echo getTemporaryDirectory('/var/tmp/myapp'); // stays as is on Unix
 * echo getTemporaryDirectory('C:\\Temp\\custom'); // stays as is on Windows
 * ```
 *
 * Edge case: skip path argument to return system temp dir directly:
 * ```php
 * echo getTemporaryDirectory(null); // same as sys_get_temp_dir()
 * echo getTemporaryDirectory('');   // same as sys_get_temp_dir()
 * ```
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
function getTemporaryDirectory( string|array|null $path = null , bool $assertable = false , bool $isReadable = true , bool $isWritable = false ): string
{
    $base = sys_get_temp_dir() ;

    if ( is_array( $path ) )
    {
        $path = array_filter($path, static fn(?string $p): bool => is_string($p) && $p !== Char::EMPTY);
        $path = implode(DIRECTORY_SEPARATOR, $path);
    }

    if ( $path !== null && $path !== Char::EMPTY )
    {
        if ( DIRECTORY_SEPARATOR === Char::SLASH && str_starts_with($path, DIRECTORY_SEPARATOR ))
        {
            $path = rtrim($path, DIRECTORY_SEPARATOR); // Unix absolute path
        }
        else if ( DIRECTORY_SEPARATOR === Char::BACK_SLASH && preg_match('#^[A-Z]:\\\\#i', $path))
        {

            $path = rtrim($path, DIRECTORY_SEPARATOR); // Windows absolute path (ex: C:\foo)
        }
        else
        {
            $path = rtrim($base . DIRECTORY_SEPARATOR . $path, DIRECTORY_SEPARATOR);
        }
    }
    else
    {
        $path = $base ;
    }

    return getDirectory( $path , $assertable , $isReadable , $isWritable ) ;
}