<?php

namespace oihana\files ;

use oihana\files\exceptions\FileException;

/**
 * Reads the checksum a checksum file records for one file, or null.
 *
 * Read as `sha256sum` writes it, see {@see checksumLine()}: the hash, then two spaces — or a space
 * and a star, its binary mode — then the file name. The hash must have the length of the
 * algorithm's (64 characters for `sha256`, 32 for `md5`). Only a line naming this file counts: a
 * checksum file copied beside the wrong file must not vouch for it. A file listing several files
 * is read line by line, and the first line naming this one is taken.
 *
 * @param string $content   What the checksum file holds.
 * @param string $filename  The name of the file, as the line writes it.
 * @param string $algorithm A hashing algorithm supported by {@see hash_algos()} (default: `'sha256'`).
 *
 * @return string|null The hash, lowercase; null when no line names the file with a well-formed one.
 *
 * @throws FileException If the algorithm is unsupported.
 *
 * @example
 * ```php
 * use function oihana\files\fileChecksum;
 * use function oihana\files\readChecksumLine;
 *
 * $recorded = readChecksumLine( file_get_contents( '/data/report.pdf.sha256' ) , 'report.pdf' ) ;
 *
 * if ( $recorded === null || $recorded !== fileChecksum( '/data/report.pdf' ) )
 * {
 *     // no checksum for this file, or the file changed since it was written
 * }
 * ```
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.7.0
 */
function readChecksumLine( string $content , string $filename , string $algorithm = 'sha256' ) :?string
{
    if ( !in_array( $algorithm , hash_algos() , true ) )
    {
        throw new FileException( sprintf( 'Unsupported hash algorithm "%s".' , $algorithm ) ) ;
    }

    $length = strlen( hash( $algorithm , '' ) ) ;

    foreach ( preg_split( '/\R/' , $content ) ?: [] as $line )
    {
        if ( preg_match( '/^([0-9a-fA-F]{' . $length . '}) [ *](.+)$/' , $line , $matches ) === 1 && $matches[ 2 ] === $filename )
        {
            return strtolower( $matches[ 1 ] ) ;
        }
    }

    return null ;
}
