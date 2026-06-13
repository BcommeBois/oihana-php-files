<?php

namespace oihana\files ;

use oihana\files\enums\FileSizeUnit;

/**
 * Formats a byte count as a human-readable string.
 *
 * Uses binary multiples (base 1024) with the {@see FileSizeUnit} symbols `B`, `KB`, `MB`, `GB`, `TB`, `PB`.
 * Byte values are rendered without decimals; larger units use `$precision` decimals.
 * Zero or negative values yield `"0 B"`. Values beyond `PB` are clamped to `PB`.
 *
 * @param int $bytes     The size in bytes (e.g. from {@see getFileSize()}).
 * @param int $precision Number of decimals for non-byte units (default: `2`).
 *
 * @return string A human-readable size, e.g. `"1.18 MB"`.
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.2.0
 *
 * @example
 * ```php
 * use function oihana\files\formatFileSize;
 *
 * formatFileSize( 0 ) ;        // "0 B"
 * formatFileSize( 512 ) ;      // "512 B"
 * formatFileSize( 1536 ) ;     // "1.5 KB"
 * formatFileSize( 1240518 ) ;  // "1.18 MB"
 * ```
 */
function formatFileSize( int $bytes , int $precision = 2 ): string
{
    $units = FileSizeUnit::ordered() ;

    if ( $bytes <= 0 )
    {
        return '0 ' . $units[ 0 ] ;
    }

    $power = (int) floor( log( $bytes , 1024 ) ) ;
    $power = min( $power , count( $units ) - 1 ) ;

    if ( $power === 0 )
    {
        return $bytes . ' ' . $units[ 0 ] ;
    }

    $value = $bytes / ( 1024 ** $power ) ;

    return round( $value , $precision ) . ' ' . $units[ $power ] ;
}
