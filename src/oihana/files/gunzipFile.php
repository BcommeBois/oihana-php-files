<?php

namespace oihana\files ;

use oihana\files\enums\FileExtension;
use oihana\files\exceptions\FileException;

/**
 * Decompresses a single gzip file, streaming it in chunks.
 *
 * Counterpart of {@see gzipFile()}, using the `zlib` extension — no subprocess,
 * and the file is never fully loaded into memory.
 *
 * @param string      $source      Path to the gzip file to decompress.
 * @param string|null $destination Output path. Defaults to `$source` without its `.gz` suffix,
 *                                 or `$source` + `.out` when the source has no `.gz` suffix.
 * @param bool        $overwrite   Whether to overwrite an existing destination (default: `true`).
 *
 * @return string The destination path.
 *
 * @throws FileException If the source is invalid, `zlib` is unavailable, the destination exists
 *                       and `$overwrite` is `false`, or decompression fails.
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.2.0
 *
 * @example
 * ```php
 * use function oihana\files\gunzipFile;
 *
 * gunzipFile( '/var/log/app.log.gz' ) ; // -> /var/log/app.log
 * ```
 */
function gunzipFile( string $source , ?string $destination = null , bool $overwrite = true ): string
{
    assertFile( $source ) ;

    if ( !function_exists( 'gzopen' ) )
    {
        // ext-zlib is present in the test environment.
        // @codeCoverageIgnoreStart
        throw new FileException( 'The "zlib" PHP extension is required to gunzip files.' ) ;
        // @codeCoverageIgnoreEnd
    }

    if ( $destination === null )
    {
        $destination = str_ends_with( $source , FileExtension::GZ )
            ? substr( $source , 0 , -strlen( FileExtension::GZ ) )
            : $source . '.out' ;
    }

    if ( !$overwrite && is_file( $destination ) )
    {
        throw new FileException( sprintf( 'The destination file "%s" already exists.' , $destination ) ) ;
    }

    $in = @gzopen( $source , 'rb' ) ;
    if ( $in === false )
    {
        // gzopen() does not fail after assertFile() on a readable file.
        // @codeCoverageIgnoreStart
        throw new FileException( sprintf( 'Failed to open the gzip source "%s".' , $source ) ) ;
        // @codeCoverageIgnoreEnd
    }

    $out = @fopen( $destination , 'wb' ) ;
    if ( $out === false )
    {
        gzclose( $in ) ;
        throw new FileException( sprintf( 'Failed to open the destination "%s".' , $destination ) ) ;
    }

    while ( !gzeof( $in ) )
    {
        fwrite( $out , gzread( $in , 8192 ) ) ;
    }

    gzclose( $in ) ;
    fclose( $out ) ;

    return $destination ;
}
