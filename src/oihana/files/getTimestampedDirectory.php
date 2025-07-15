<?php

namespace oihana\files ;

use Throwable;

use oihana\enums\Char;
use oihana\files\exceptions\DirectoryException;

use function oihana\core\date\formatDateTime;

/**
 * Get a timestamped file path using a formatted date and optional prefix/suffix.
 *
 * Combines a date/time string (or the current time) with optional *extension, *prefix*, *suffix*,
 * and base path to generate a unique file name. The file is not created on disk.
 *
 * Asserts by default if the file exist, you can disabled the behavior with the boolean *assertable* argument.
 *
 * @param string|null $date Optional date/time string to use. If null or invalid, the current date/time is used ("now").
 * @param string $basePath Optional base path in which to place the directory. Defaults to the current directory.
 * @param string $prefix Optional string to prepend to the directory name (e.g., "/hello-2025-12-01T14:00:00"").
 * @param string $suffix Optional string to append to the directory name (e.g., "/2025-12-01T14:00:00-hello"").
 * @param string|null $timezone Timezone identifier (e.g., 'Europe/Paris'). Defaults to 'Europe/Paris'.
 * @param string|null $format Date format compatible with DateTime::format(). Defaults to 'Y-m-d\TH:i:s'.
 * @param bool $assertable Whether to validate the path with assertDirectory(). Defaults to true.
 *
 * @return string The full path of the generated directory.
 *
 * @throws DirectoryException If the directory path is invalid or assertion fails.
 *
 * @example
 * ```php
 * use function oihana\files\getTimestampedDirectory;
 *
 * // Example 1: Generate directory path with current date/time, no prefix/suffix, current directory base
 * $dirPath = getTimestampedDirectory();
 * // e.g. "./2025-07-15T14:32:00"
 *
 * // Example 2: Generate directory path with specific date, custom base path, and extension-like suffix
 * $dirPath = getTimestampedDirectory(
 * date: '2025-12-01 14:00:00',
 * basePath: '/var/backups',
 * suffix: '_archive'
 * );
 * // e.g. "/var/backups/2025-12-01T14:00:00_archive"
 *
 * // Example 3: Add prefix and suffix, custom timezone and format, without assertion
 * $dirPath = getTimestampedDirectory(
 * prefix: 'backup_',
 * suffix: '_final',
 * timezone: 'UTC',
 * format: 'Ymd_His',
 * assertable: false
 * );
 * // e.g. "./backup_20250715_123200_final"
 * ```
 */
function getTimestampedDirectory
(
    ?string $date       = null ,
     string $basePath   = Char::EMPTY ,
     string $prefix     = Char::EMPTY ,
     string $suffix     = Char::EMPTY ,
    ?string $timezone   = 'Europe/Paris' ,
    ?string $format     = 'Y-m-d\TH:i:s' ,
       bool $assertable = true ,
) :string
{
    try
    {
        $basePath = rtrim( $basePath , DIRECTORY_SEPARATOR ) ;

        $directory = ( $basePath === Char::EMPTY ? Char::EMPTY : $basePath . DIRECTORY_SEPARATOR )
                   . $prefix
                   . formatDateTime( $date , $timezone , $format )
                   . $suffix ;

        $directory = rtrim( $directory , DIRECTORY_SEPARATOR ) ;

        if( $assertable )
        {
            assertDirectory( $directory ) ;
        }

        return $directory ;
    }
    catch ( Throwable $exception )
    {
        throw new DirectoryException( 'Failed to returns a timestamped directory.' , $exception->getCode() , $exception ) ;
    }
}