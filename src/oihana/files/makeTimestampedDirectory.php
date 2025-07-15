<?php

namespace oihana\files ;

use Throwable;

use oihana\enums\Char;
use oihana\files\exceptions\DirectoryException;

use function oihana\core\date\formatDateTime;

/**
 * Creates a directory named with a formatted timestamp.
 *
 * Combines a date/time string (or the current time) with optional prefix, suffix,
 * and base path to generate a unique directory name. The directory is created
 * if it does not already exist.
 *
 * @param string|null $date Optional date/time string to use. If null or invalid, the current date/time is used ("now").
 * @param string $basePath Optional base path in which to create the directory. Defaults to an empty string.
 * @param string $prefix Optional string to prepend to the directory name.
 * @param string $suffix Optional string to append to the directory name.
 * @param string|null $timezone Timezone identifier (e.g., 'Europe/Paris'). Defaults to 'Europe/Paris'.
 * @param string|null $format Date format compatible with DateTime::format(). Defaults to 'Y-m-d\TH:i:s'.
 *
 * @return string|null The full path of the created directory, or null on failure.
 *
 * @throws DirectoryException If directory creation fails due to an error.
 *
 * @example
 * ```php
 * use oihana\\files\\createTimestampedDirectory;
 * use oihana\\enums\\Char;
 *
 * // Example 1: simple directory in current path, using current date‑time
 * $dir = createTimestampedDirectory();
 * // e.g. ./2025-07-15T10:30:12
 *
 * // Example 2 : directory inside /tmp with custom prefix/suffix and explicit date
 * $dir = createTimestampedDirectory(
 * date:     '2025-12-01 14:00:00',
 * basePath: '/tmp',
 * prefix:   'backup_',
 * suffix:   '_v1'
 * );
 * // e.g. /tmp/backup_2025-12-01T14:00:00_v1
 *
 * // Example 3 : use a different timezone and format
 * $dir = createTimestampedDirectory(
 * date:     null,              // now
 * basePath: Char::EMPTY,       // current directory
 * prefix:   'log_',
 * suffix:   Char::EMPTY,
 * timezone: 'UTC',
 * format:   'Ymd_His'          // 20250715_083012
 * );
 * // e.g. ./log_20250715_083012
 * ```
 */
function makeTimestampedDirectory
(
    ?string $date      = null ,
     string $basePath  = Char::EMPTY ,
     string $prefix    = Char::EMPTY ,
     string $suffix    = Char::EMPTY ,
    ?string $timezone  = 'Europe/Paris' ,
    ?string $format    = 'Y-m-d\TH:i:s'
):?string
{
    try
    {
        $timestamp     = formatDateTime( $date , $timezone , $format ) ;
        $directoryName = $prefix . $timestamp . $suffix;
        $fullPath      = rtrim( $basePath , DIRECTORY_SEPARATOR ) ;

        $directory = $fullPath !== Char::EMPTY
                   ? $fullPath . DIRECTORY_SEPARATOR . $directoryName
                   : $directoryName ;

        if ( !is_dir( $directory ) && !mkdir( $directory , 0755 , true ) )
        {
            throw new DirectoryException("Unable to create directory: {$directory}" ) ;
        }

        return $directory ;
    }
    catch ( Throwable $exception )
    {
        throw new DirectoryException( 'Failed to creates a timestamped directory.' , $exception->getCode() , $exception ) ;
    }
}