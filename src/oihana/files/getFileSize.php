<?php

namespace oihana\files ;

use oihana\files\exceptions\FileException;

/**
 * Returns the size of a file, in bytes.
 *
 * A thin, exception-throwing wrapper around `filesize()`: the file is first
 * validated with {@see assertFile()} (must exist and be readable), so a missing
 * file raises a typed {@see FileException} rather than a warning + `false`.
 *
 * @param string $file Path to the file to measure.
 *
 * @return int The file size in bytes.
 *
 * @throws FileException If the file is missing, unreadable, or its size cannot be read.
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.2.0
 *
 * @see formatFileSize() To render the returned byte count as a human-readable string.
 *
 * @example
 * ```php
 * use function oihana\files\getFileSize;
 *
 * $bytes = getFileSize( '/data/report.pdf' ) ; // e.g. 1240518
 * ```
 */
function getFileSize( string $file ): int
{
    assertFile( $file ) ;

    clearstatcache() ;

    $size = filesize( $file ) ;

    if ( $size === false )
    {
        // filesize() does not fail after assertFile() on a readable file.
        // @codeCoverageIgnoreStart
        throw new FileException( sprintf( 'Failed to read the size of file "%s".' , $file ) ) ;
        // @codeCoverageIgnoreEnd
    }

    return $size ;
}
