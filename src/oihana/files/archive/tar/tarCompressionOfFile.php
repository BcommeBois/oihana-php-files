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
 * **Scope: gzip, bzip2, or none.** These are the compressions this library writes
 * ({@see \oihana\files\enums\TarExtension::getExtensionForCompression()}) and the only
 * ones {@see \oihana\files\archive\tar\hasTarExtension()} lets through, so an `xz`
 * archive never reaches here. Recognising one would be a branch no caller could take —
 * the kind of dead code this file deliberately avoids. The day `.tar.xz` is accepted
 * upstream, this function gets one line and one test.
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

    $magic = @fread( $handle , 3 ) ;

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

    return null ;
}
