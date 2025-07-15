<?php

namespace oihana\files ;

use oihana\files\exceptions\FileException;
use Throwable;

use oihana\enums\Char;

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
 * @param string $basePath Optional base path in which to place the file. Defaults to the current directory.
 * @param ?string $extension Optional extension to append to the file name (e.g., ".log", ".txt").
 * @param string $prefix Optional string to prepend to the file name.
 * @param string $suffix Optional string to append to the file name (e.g., "2025-12-01T14:00:00-hello"").
 * @param string|null $timezone Timezone identifier (e.g., 'Europe/Paris'). Defaults to 'Europe/Paris'.
 * @param string|null $format Date format compatible with DateTime::format(). Defaults to 'Y-m-d\TH:i:s'.
 * @param bool $assertable Whether to validate the path with assertFile(). Defaults to true.
 *
 * @return string|null The full path of the generated file, or null on failure.
 *
 * @throws FileException If the file path is invalid.
 *
 * @example
 * ```php
 * use oihana\files\makeTimestampedFile;
 * use oihana\enums\Char;
 *
 * // Example 1: file in the current directory using default format
 * $file = getTimestampedFile();
 * // e.g. ./2025-07-15T10:45:33
 *
 * // Example 2: file inside /tmp with prefix and suffix
 * $file = getTimestampedFile
 * (
 *     date:     '2025-12-01 14:00:00',
 *     basePath: '/tmp',
 *     prefix:   'backup_',
 *     suffix:   '.sql'
 * );
 * // e.g. /tmp/backup_2025-12-01T14:00:00.sql
 *
 * // Example 3: use a different timezone and format
 * $file = getTimestampedFile
 * (
 *     basePath : Char::EMPTY,
 *     prefix   : 'log_',
 *     suffix   : '.txt',
 *     timezone : 'UTC',
 *     format   : 'Ymd_His'
 * );
 * // e.g. ./log_20250715_084533.txt
 * ```
 */
function getTimestampedFile
(
    ?string $date       = null ,
     string $basePath   = Char::EMPTY ,
    ?string $extension  = null ,
     string $prefix     = Char::EMPTY ,
     string $suffix     = Char::EMPTY ,
    ?string $timezone   = 'Europe/Paris' ,
    ?string $format     = 'Y-m-d\TH:i:s' ,
       bool $assertable = true ,
) :?string
{
    try
    {
        $timestamp = formatDateTime( $date, $timezone, $format );

        $fileName = $prefix . $timestamp . $suffix ;

        if ( $extension !== null && $extension !== Char::EMPTY )
        {
            $fileName .= $extension ;
        }

        $path = $basePath !== Char::EMPTY
            ? rtrim( $basePath , DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR . $fileName
            : $fileName ;

        if( $assertable )
        {
            assertFile( $path ) ;
        }

        return $path ;
    }
    catch ( Throwable $exception )
    {
        throw new FileException
        (
            'Failed to generate a timestamped file path.' ,
            $exception->getCode() ,
            $exception
        ) ;
    }
}