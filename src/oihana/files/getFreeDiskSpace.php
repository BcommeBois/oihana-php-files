<?php

namespace oihana\files ;

use oihana\files\exceptions\FileException;

/**
 * Returns the number of free bytes on the filesystem hosting a directory.
 *
 * Typed wrapper around `disk_free_space()`: an invalid path raises a
 * {@see FileException} instead of returning `false`. Useful as a pre-flight
 * guard before extracting an archive or writing large files.
 *
 * @param string $directory A directory (or path) on the filesystem to inspect (default: `'.'`).
 *
 * @return float The number of available bytes.
 *
 * @throws FileException If the free space cannot be determined (e.g. invalid path).
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.2.0
 *
 * @see getTotalDiskSpace()
 * @see getDiskUsage()
 *
 * @example
 * ```php
 * use function oihana\files\getFreeDiskSpace;
 *
 * if ( getFreeDiskSpace( '/var/www' ) < $estimatedSize )
 * {
 *     throw new RuntimeException( 'Not enough free disk space.' ) ;
 * }
 * ```
 */
function getFreeDiskSpace( string $directory = '.' ): float
{
    $bytes = @disk_free_space( $directory ) ;

    if ( $bytes === false )
    {
        throw new FileException( sprintf( 'Failed to read the free disk space for "%s".' , $directory ) ) ;
    }

    return $bytes ;
}
