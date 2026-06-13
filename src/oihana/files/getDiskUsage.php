<?php

namespace oihana\files ;

use oihana\files\exceptions\FileException;

/**
 * Returns the number of used bytes on the filesystem hosting a directory.
 *
 * Computed as {@see getTotalDiskSpace()} − {@see getFreeDiskSpace()}, so the
 * result shares the same unit (bytes) as the two helpers it builds on. Pair it
 * with {@see formatFileSize()} for a human-readable value, or divide by
 * {@see getTotalDiskSpace()} for a usage ratio.
 *
 * @param string $directory A directory (or path) on the filesystem to inspect (default: `'.'`).
 *
 * @return float The number of used bytes.
 *
 * @throws FileException If the disk space cannot be determined (e.g. invalid path).
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.2.0
 *
 * @see getFreeDiskSpace()
 * @see getTotalDiskSpace()
 *
 * @example
 * ```php
 * use function oihana\files\{ getDiskUsage , formatFileSize };
 *
 * echo formatFileSize( (int) getDiskUsage( '/' ) ) ; // e.g. "187.4 GB"
 * ```
 */
function getDiskUsage( string $directory = '.' ): float
{
    return getTotalDiskSpace( $directory ) - getFreeDiskSpace( $directory ) ;
}
