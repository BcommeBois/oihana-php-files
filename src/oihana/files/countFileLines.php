<?php

namespace oihana\files ;

use oihana\files\exceptions\FileException;

/**
 * Counts the number of lines in a file.
 *
 * This function efficiently counts lines by reading the file in chunks,
 * making it suitable even for very large files.
 *
 * Example usage:
 * ```php
 * use function oihana\files\countFileLines;
 *
 * $count = countFileLines('/path/to/file.log');
 * ```
 *
 * @param string|null $file The full path to the file.
 *
 * @return int The total number of lines in the file.
 *
 * @throws FileException If the file does not exist, is not readable, or cannot be opened.
 *
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 * @package oihana\files
 */
function countFileLines( ?string $file ): int
{
    assertFile( $file ) ;

    clearstatcache();

    if ( filesize( $file ) === 0 )
    {
        return 0;
    }

    $handle = @fopen( $file, 'r' ) ;

    if (!$handle)
    {
        throw new FileException("Unable to open file for reading: {$file}" ) ;
    }

    $lines = 0;

    // Lecture par chunks pour plus d'efficacité
    while (!feof($handle))
    {
        $chunk = fread( $handle , 8192 ) ;
        $lines += substr_count( $chunk , "\n" ) ;
    }

    fclose( $handle ) ;

    return $lines;
}