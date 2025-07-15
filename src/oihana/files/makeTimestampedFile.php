<?php

namespace oihana\files ;

use oihana\files\exceptions\FileException;
use Throwable;

use oihana\enums\Char;

/**
 * Generates a timestamped file path if not exist. Using a formatted date and optional prefix/suffix.
 *
 * Combines a date/time string (or the current time) with optional prefix, suffix,
 * and base path to generate a unique file name. The file is not created on disk.
 *
 * @param string|null $date Optional date/time string to use. If null or invalid, the current date/time is used ("now").
 * @param string $basePath Optional base path in which to place the file. Defaults to the current directory.
 * @param ?string $extension Optional extension to append to the file name (e.g., ".log", ".txt").
 * @param string $prefix Optional string to prepend to the file name.
 * @param string $suffix Optional string to append to the file name (e.g., "2025-12-01T14:00:00-hello"").
 * @param string|null $timezone Timezone identifier (e.g., 'Europe/Paris'). Defaults to 'Europe/Paris'.
 * @param string|null $format Date format compatible with DateTime::format(). Defaults to 'Y-m-d\TH:i:s'.
 * @param bool $mustExist Whether the generated file must exist. If true, asserts the file exists. Defaults to false.
 *
 * @return string|null The full path of the generated file, or null on failure.
 *
 * @throws FileException If the file path is invalid, not writable, or must exist but does not.
 * @example
 * ```php
 * use oihana\files\makeTimestampedFile;
 *
 * // Example 1: Generate a file path with current datetime in default format, no prefix/suffix
 * $filePath = makeTimestampedFile();
 * // e.g. "./2025-07-15T10:45:33"
 *
 * // Example 2: Generate a file path with a specific date and extension inside /tmp
 * $filePath = makeTimestampedFile(
 *     date: '2025-12-01 14:00:00',
 *     basePath: '/tmp',
 *     extension: '.log'
 * );
 * // e.g. "/tmp/2025-12-01T14:00:00.log"
 *
 * // Example 3: Add prefix and suffix, use a custom timezone and date format
 * $filePath = makeTimestampedFile(
 *     prefix: 'backup_',
 *     suffix: '_final',
 *     timezone: 'UTC',
 *     format: 'Ymd_His'
 * );
 * // e.g. "./backup_20250715_084533_final"
 *
 * // Example 4: Require that the generated file already exists (throws if not)
 * $filePath = makeTimestampedFile(mustExist: true);
 * ```
 */
function makeTimestampedFile
(
    ?string $date      = null ,
     string $basePath  = Char::EMPTY ,
    ?string $extension = null ,
     string $prefix    = Char::EMPTY ,
     string $suffix    = Char::EMPTY ,
    ?string $timezone  = 'Europe/Paris' ,
    ?string $format    = 'Y-m-d\TH:i:s' ,
       bool $mustExist = false ,
) :?string
{
    try
    {
        $path = getTimestampedFile( $date ,$basePath , $extension , $prefix , $suffix , $timezone , $format , $mustExist );
        if ( !@touch($path ) )
        {
            throw new FileException("Cannot create or write to file: $path" ) ;
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