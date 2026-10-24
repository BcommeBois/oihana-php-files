<?php

namespace oihana\files\archive\tar ;

/**
 * Whether the system `tar` can extract this archive here.
 *
 * Two conditions, and the second is the one that is easy to forget: `tar` does not
 * decompress anything itself. It pipes through `gzip` or `bzip2`, so a host with
 * `ext-zlib` and no `gzip` program has a perfectly good GNU tar that cannot open a
 * `.tar.gz`. PharData, which decompresses through the PHP extensions, can — which is
 * why the fallback exists and why this question is asked before anything is extracted.
 *
 * The binary itself comes from {@see tarBinary()}, which accepts GNU tar only, having
 * run it to ask. `OIHANA_TAR_BINARY` declared empty forces `false` here, which is the
 * seam the tests use to run the whole extraction suite through PharData on a machine
 * that has a usable tar.
 *
 * @param string $tarFile Path to the archive.
 *
 * @return bool True when {@see untarWithBinary()} can be used on it.
 *
 * @example
 * ```php
 * use function oihana\files\archive\tar\untarBinaryHandles;
 *
 * untarBinaryHandles('/backups/site.tar.gz') ; // true where GNU tar and gzip are both present
 * ```
 *
 * @package oihana\files\archive\tar
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.5.0
 */
function untarBinaryHandles( string $tarFile ) :bool
{
    if ( tarBinary() === null )
    {
        return false ;
    }

    $compression = tarCompressionOfFile( $tarFile ) ;

    return $compression === null || tarCompressorExists( $compression ) ;
}
