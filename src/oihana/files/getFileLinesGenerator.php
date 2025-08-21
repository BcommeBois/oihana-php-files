<?php

namespace oihana\files ;

use Generator;
use oihana\files\exceptions\FileException;

/**
 * Reads a file line by line and yields each line as a generator.
 *
 * Each line can optionally be transformed using a callback function.
 * This is particularly useful for processing large files efficiently without
 * loading the entire file into memory at once.
 *
 * Example usage:
 * ```php
 * use function oihana\files\getFileLinesGenerator;
 *
 * // Simply iterate over each line
 * foreach (getFileLinesGenerator('/path/to/file.log') as $line)
 * {
 *     echo $line, PHP_EOL;
 * }
 *
 * // Using a mapping function to parse CSV lines
 * foreach (getFileLinesGenerator('/path/to/data.csv', fn($line) => str_getcsv($line)) as $csvRow)
 * {
 *     print_r($csvRow);
 * }
 * ```
 *
 * @param string|null    $file Full path to the file to read.
 * @param callable|null  $map  Optional mapping function applied to each line. Signature: fn(string $line): mixed
 *
 * @return Generator Yields each line of the file, optionally transformed by the mapping function.
 *
 * @throws FileException If the file does not exist, is not readable, or cannot be opened.
 *
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 * @package oihana\files
 */
function getFileLinesGenerator( ?string $file , ?callable $map = null ): Generator
{
    assertFile( $file ) ;

    $handle = fopen( $file, 'r' ) ;

    if ( $handle === false )
    {
        throw new FileException("Unable to open file: $file" ) ;
    }

    try
    {
        while ( ( $line = fgets( $handle ) ) !== false )
        {
            $line = rtrim( $line, "\r\n" ) ;
            yield $map ? $map($line) : $line ;
        }
    }
    finally
    {
        fclose($handle);
    }
}