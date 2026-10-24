<?php

namespace oihana\files\archive\tar ;

use oihana\files\enums\CompressionType;

/**
 * Reports how an archive is compressed, read from its own first bytes.
 *
 * Not from its name. `tarIsCompressed()` answers the same question from the extension,
 * which is all that is needed to pick a code path for an archive this library wrote —
 * but an archive handed to {@see untar()} may be named anything, and the system `tar`
 * has to be told nothing it cannot verify. Three bytes settle it.
 *
 * **Scope: gzip, bzip2, xz, zstd, or none** — the compressions this library writes
 * ({@see \oihana\files\enums\TarExtension::getExtensionForCompression()}) and the ones
 * {@see hasTarExtension} lets through. `xz` and `zstd`
 * joined in 1.5.0, and this function is one of the six places that had to follow for an
 * archive to be readable as well as writable: a format written and not read back is the
 * defect that lot was fixing.
 *
 * @param string $tarFile Path to the archive.
 *
 * @return string|null {@see CompressionType::GZIP}, {@see CompressionType::BZIP2}, or
 *                     `null` for an uncompressed archive — and for one that cannot be
 *                     read, since an unreadable file is not a compressed one.
 *
 * @example
 * ```php
 * use function oihana\files\archive\tar\tarCompressionOfFile;
 *
 * tarCompressionOfFile('/tmp/site.tar.gz') ;  // 'gzip'
 * tarCompressionOfFile('/tmp/site.tar.zst') ; // 'zstd'
 * tarCompressionOfFile('/tmp/site.tar') ;     // null
 * ```
 *
 * @package oihana\files\archive\tar
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.5.0
 */
function tarCompressionOfFile( string $tarFile ) :?string
{
    $handle = @fopen( $tarFile , 'rb' ) ;

    if ( $handle === false )
    {
        return null ;
    }

    // Six bytes: the longest magic to recognise is xz's `fd 37 7a 58 5a 00`.
    $magic = @fread( $handle , 6 ) ;

    fclose( $handle ) ;

    if ( !is_string( $magic ) )
    {
        // Reachable only if the file disappears between the open and the read.
        // @codeCoverageIgnoreStart
        return null ;
        // @codeCoverageIgnoreEnd
    }

    if ( str_starts_with( $magic , "\x1f\x8b" ) )
    {
        return CompressionType::GZIP ;
    }

    if ( str_starts_with( $magic , 'BZh' ) )
    {
        return CompressionType::BZIP2 ;
    }

    if ( str_starts_with( $magic , "\xfd7zXZ" ) )
    {
        return CompressionType::XZ ;
    }

    if ( str_starts_with( $magic , "\x28\xb5\x2f\xfd" ) )
    {
        return CompressionType::ZSTD ;
    }

    return null ;
}
