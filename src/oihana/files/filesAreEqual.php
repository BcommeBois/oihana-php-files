<?php

namespace oihana\files ;

use oihana\files\exceptions\FileException;

/**
 * Tells whether two files have identical contents.
 *
 * The comparison is short-circuited for speed:
 *
 * 1. if both paths resolve to the **same file** on disk, returns `true` without reading;
 * 2. if the file **sizes differ**, returns `false` without hashing;
 * 3. otherwise the files are compared by {@see fileChecksum()}.
 *
 * @param string $a         Path to the first file.
 * @param string $b         Path to the second file.
 * @param string $algorithm A hashing algorithm supported by {@see hash_algos()} (default: `'sha256'`).
 *
 * @return bool `true` if both files have identical contents, `false` otherwise.
 *
 * @throws FileException If either file is missing or unreadable, or the algorithm is unsupported.
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.2.0
 *
 * @example
 * ```php
 * use function oihana\files\filesAreEqual;
 *
 * if ( filesAreEqual( '/data/a.bin' , '/backup/a.bin' ) )
 * {
 *     // contents match — safe to deduplicate
 * }
 * ```
 */
function filesAreEqual( string $a , string $b , string $algorithm = 'sha256' ): bool
{
    $realA = realpath( $a ) ;
    if ( $realA !== false && $realA === realpath( $b ) )
    {
        return true ;
    }

    assertFile( $a ) ;
    assertFile( $b ) ;

    if ( filesize( $a ) !== filesize( $b ) )
    {
        return false ;
    }

    return fileChecksum( $a , $algorithm ) === fileChecksum( $b , $algorithm ) ;
}
