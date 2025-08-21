<?php

namespace oihana\files ;

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
 * ```
 *
 * @param string|null    $file The full path to the file to read.
 * @param callable|null  $map  Optional mapping function applied to each line. Signature: fn(string $line): mixed
 *
 * @return array|null Returns an array of lines (or mapped values). Returns an empty array if the file is empty.
 *
 * @throws FileException If the file does not exist, is not readable, or cannot be opened.
 *
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 * @package oihana\files
 */
function getFileLines( ?string $file, ?callable $map = null): ?array
{
    assertFile( $file ) ;

    clearstatcache() ;

    if ( filesize( $file ) === 0 )
    {
        return [];
    }

    return iterator_to_array( getFileLinesGenerator( $file , $map ) ) ;
}