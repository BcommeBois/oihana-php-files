<?php

namespace oihana\files ;

use RuntimeException;

use oihana\files\exceptions\FileException;

/**
 * Reads the entire contents of a file into a string.
 *
 * Symmetric counterpart of {@see makeFile()} (which writes a file): where
 * {@see getFileLines()} reads a file line by line, this helper returns the whole
 * content as a single string.
 *
 * @param string|null $file     The full path to the file to read.
 * @param int|null    $maxBytes Optional cap on the file size (in bytes). When set, the file is rejected
 *                              **before** being read if its size exceeds this value, throwing
 *                              {@see \RuntimeException}. Default `null` (no limit). Useful as a defensive
 *                              guard against OOM when the caller does not fully trust the input size.
 *
 * @return string The full file contents. An empty string for an empty file.
 *
 * @throws FileException    If the file does not exist, is not readable, or cannot be read.
 * @throws RuntimeException If the file size exceeds `$maxBytes`.
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.2.0
 *
 * @example
 * ```php
 * use function oihana\files\getFileContent;
 *
 * $content = getFileContent( '/path/to/config.json' ) ;
 *
 * // Refusing files larger than 10 MiB (defensive cap on untrusted sources).
 * $content = getFileContent( '/path/to/upload.bin' , 10 * 1024 * 1024 ) ;
 * ```
 */
function getFileContent( ?string $file , ?int $maxBytes = null ): string
{
    assertFile( $file ) ;

    clearstatcache() ;

    $size = filesize( $file ) ;

    if ( $maxBytes !== null && $size > $maxBytes )
    {
        throw new RuntimeException( sprintf
        (
            'getFileContent() aborted: file "%s" is %d bytes, exceeds maximum %d bytes.' ,
            $file ,
            $size ,
            $maxBytes
        ) ) ;
    }

    $content = @file_get_contents( $file ) ;

    if ( $content === false )
    {
        // file_get_contents() does not fail after assertFile() on a readable file.
        // @codeCoverageIgnoreStart
        throw new FileException( sprintf( 'Failed to read file "%s".' , $file ) ) ;
        // @codeCoverageIgnoreEnd
    }

    return $content ;
}
