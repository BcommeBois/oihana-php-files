<?php

namespace oihana\files\openssl ;

/**
 * Parses a `memory_limit`-style size into bytes, or `null` when the value names no
 * usable ceiling.
 *
 * Accepts a plain byte count, optionally suffixed with `K`, `M` or `G`
 * (case-insensitive), with optional surrounding whitespace — the shapes PHP itself
 * reads as a size. `null` comes back for:
 *
 * - `-1`, the value that means *unlimited*: there is no ceiling to report;
 * - `0` or a negative count, which name no usable budget;
 * - an empty string, or anything that is not a number followed by at most one of
 *   those three suffixes.
 *
 * That last case includes `'128MB'`, and deliberately. PHP reads a quantity's
 * multiplier from what follows the digits, accepts only a single `k`, `m` or `g`
 * there, and warns then **ignores** anything else — so `memory_limit = 128MB` is
 * 128 *bytes* to PHP, not 128 mebibytes. Returning the number this function would
 * like it to mean would overstate the budget by a factor of a million; returning
 * `null` says *unknown*, which is the one answer that cannot mislead a caller.
 *
 * @param string $value The raw setting value, e.g. `'512M'`, `'1G'`, `'134217728'`, `'-1'`.
 *
 * @return int|null The size in bytes, or `null` when the value names no usable ceiling.
 *
 * @example
 * ```php
 * use function oihana\files\openssl\parseMemoryLimit;
 *
 * parseMemoryLimit('256M')      ; // 268435456
 * parseMemoryLimit('1G')        ; // 1073741824
 * parseMemoryLimit('134217728') ; // 134217728
 * parseMemoryLimit('-1')        ; // null — unlimited, so no ceiling
 * parseMemoryLimit('128MB')     ; // null — PHP would read 128 bytes here
 * ```
 *
 * @package oihana\files\openssl
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.5.0
 */
function parseMemoryLimit( string $value ): ?int
{
    if ( preg_match( '/^\s*(-?\d+)\s*([KMG]?)\s*$/i' , $value , $matches ) !== 1 )
    {
        return null ;
    }

    $size = (int) $matches[ 1 ] ;

    if ( $size <= 0 )
    {
        return null ;
    }

    return match ( strtoupper( $matches[ 2 ] ) )
    {
        'K'     => $size * 1024 ,
        'M'     => $size * 1024 * 1024 ,
        'G'     => $size * 1024 * 1024 * 1024 ,
        default => $size
    } ;
}
