<?php

namespace oihana\files ;

use oihana\files\exceptions\FileException;

/**
 * Computes the checksum (hash) of a file's contents.
 *
 * Reads the file through `hash_file()`, so the whole file does not need to be
 * loaded into memory. Useful for integrity checks, deduplication, and verifying
 * extracted archives.
 *
 * @param string $file      Path to the file to hash.
 * @param string $algorithm A hashing algorithm supported by {@see hash_algos()} (default: `'sha256'`).
 *
 * @return string The computed hash, lowercase hexadecimal.
 *
 * @throws FileException If the file is missing or unreadable, the algorithm is unsupported,
 *                       or the hash computation fails.
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.2.0
 *
 * @example
 * ```php
 * use function oihana\files\fileChecksum;
 *
 * $sha256 = fileChecksum( '/data/report.pdf' ) ;            // default sha256
 * $md5    = fileChecksum( '/data/report.pdf' , 'md5' ) ;    // explicit algorithm
 * ```
 */
function fileChecksum( string $file , string $algorithm = 'sha256' ): string
{
    assertFile( $file ) ;

    if ( !in_array( $algorithm , hash_algos() , true ) )
    {
        throw new FileException( sprintf( 'Unsupported hash algorithm "%s".' , $algorithm ) ) ;
    }

    $hash = hash_file( $algorithm , $file ) ;

    if ( $hash === false )
    {
        // hash_file() does not fail after assertFile() with a validated algorithm.
        // @codeCoverageIgnoreStart
        throw new FileException( sprintf( 'Failed to compute the "%s" checksum of "%s".' , $algorithm , $file ) ) ;
        // @codeCoverageIgnoreEnd
    }

    return $hash ;
}
