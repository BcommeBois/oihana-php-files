<?php

namespace oihana\files ;

use RuntimeException;

use oihana\files\exceptions\FileException;

/**
 * Retrieves all lines from a file as an array, optionally transforming each line with a callback.
 *
 * This function uses a generator internally (`getFileLinesGenerator`) to read the file line by line,
 * which allows efficient processing of large files. Each line can optionally be mapped using the
 * provided callable.
 *
 * Example usage:
 * ```php
 * use function oihana\files\getFileLines;
 *
 * $lines = getFileLines('/path/to/file.log');
 *
 * // Using a mapping function to parse CSV lines
 * $csvLines = getFileLines('/path/to/data.csv', fn($line) => str_getcsv($line));
 *
 * // Refusing files larger than 10 MiB (defensive cap on untrusted sources).
 * $lines = getFileLines('/path/to/upload.log', null, 10 * 1024 * 1024);
 * ```
 *
 * @param string|null    $file     The full path to the file to read.
 * @param callable|null  $map      Optional mapping function applied to each line. Signature: fn(string $line): mixed
 * @param int|null       $maxBytes Optional cap on the file size (in bytes). When set, `getFileLines()` rejects any
 *                                 file whose size exceeds this value **before** opening it, throwing
 *                                 {@see \RuntimeException}. Default `null` (no limit — historical behaviour).
 *                                 Useful as a defensive guard against OOM when the caller does not fully trust
 *                                 the size of the input.
 *
 * @return array|null Returns an array of lines (or mapped values). Returns an empty array if the file is empty.
 *
 * @throws FileException    If the file does not exist, is not readable, or cannot be opened.
 * @throws RuntimeException If the file size exceeds `$maxBytes`.
 *
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 * @package oihana\files
 */
function getFileLines( ?string $file, ?callable $map = null, ?int $maxBytes = null ): ?array
{
    assertFile( $file ) ;

    clearstatcache() ;

    $size = filesize( $file ) ;

    if ( $maxBytes !== null && $size > $maxBytes )
    {
        throw new RuntimeException( sprintf
        (
            'getFileLines() aborted: file "%s" is %d bytes, exceeds maximum %d bytes.' ,
            $file ,
            $size ,
            $maxBytes
        ) ) ;
    }

    if ( $size === 0 )
    {
        return [];
    }

    return iterator_to_array( getFileLinesGenerator( $file , $map ) ) ;
}