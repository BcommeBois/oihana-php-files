<?php

namespace oihana\files ;

use oihana\files\exceptions\FileException;

/**
 * Returns the total size, in bytes, of the filesystem hosting a directory.
 *
 * Typed wrapper around `disk_total_space()`: an invalid path raises a
 * {@see FileException} instead of returning `false`.
 *
 * @param string $directory A directory (or path) on the filesystem to inspect (default: `'.'`).
 *
 * @return float The total number of bytes of the filesystem.
 *
 * @throws FileException If the total space cannot be determined (e.g. invalid path).
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.2.0
 *
 * @see getFreeDiskSpace()
 * @see getDiskUsage()
 *
 * @example
 * ```php
 * use function oihana\files\getTotalDiskSpace;
 *
 * $total = getTotalDiskSpace( '/' ) ;
 * ```
 */
function getTotalDiskSpace( string $directory = '.' ): float
{
    $bytes = @disk_total_space( $directory ) ;

    if ( $bytes === false )
    {
        throw new FileException( sprintf( 'Failed to read the total disk space for "%s".' , $directory ) ) ;
    }

    return $bytes ;
}
