<?php

namespace oihana\files ;

use oihana\files\enums\FileExtension;
use oihana\files\exceptions\FileException;

/**
 * Compresses a single file with gzip (DEFLATE), streaming it in chunks.
 *
 * Standalone gzip compression outside of tar archives, using the `zlib`
 * extension — no subprocess, and the file is never fully loaded into memory.
 *
 * @param string      $source      Path to the file to compress.
 * @param string|null $destination Output path. Defaults to `$source` + `.gz`.
 * @param int         $level       Compression level `0`–`9`, or `-1` for zlib's default (default: `-1`).
 * @param bool        $overwrite   Whether to overwrite an existing destination (default: `true`).
 *
 * @return string The destination path.
 *
 * @throws FileException If the source is invalid, `zlib` is unavailable, the destination exists
 *                       and `$overwrite` is `false`, or compression fails.
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.2.0
 *
 * @example
 * ```php
 * use function oihana\files\gzipFile;
 *
 * gzipFile( '/var/log/app.log' ) ;                 // -> /var/log/app.log.gz
 * gzipFile( '/data/dump.sql' , '/data/dump.gz' , 9 ) ;
 * ```
 */
function gzipFile( string $source , ?string $destination = null , int $level = -1 , bool $overwrite = true ): string
{
    assertFile( $source ) ;

    if ( !function_exists( 'gzopen' ) )
    {
        // ext-zlib is present in the test environment.
        // @codeCoverageIgnoreStart
        throw new FileException( 'The "zlib" PHP extension is required to gzip files.' ) ;
        // @codeCoverageIgnoreEnd
    }

    $destination ??= $source . FileExtension::GZ ;

    if ( !$overwrite && is_file( $destination ) )
    {
        throw new FileException( sprintf( 'The destination file "%s" already exists.' , $destination ) ) ;
    }

    $in = @fopen( $source , 'rb' ) ;
    if ( $in === false )
    {
        // fopen() does not fail after assertFile() on a readable file.
        // @codeCoverageIgnoreStart
        throw new FileException( sprintf( 'Failed to open the source file "%s".' , $source ) ) ;
        // @codeCoverageIgnoreEnd
    }

    $mode = 'wb' . ( ( $level >= 0 && $level <= 9 ) ? $level : '' ) ;
    $out  = @gzopen( $destination , $mode ) ;
    if ( $out === false )
    {
        fclose( $in ) ;
        throw new FileException( sprintf( 'Failed to open the gzip destination "%s".' , $destination ) ) ;
    }

    while ( !feof( $in ) )
    {
        gzwrite( $out , fread( $in , 8192 ) ) ;
    }

    fclose( $in ) ;
    gzclose( $out ) ;

    return $destination ;
}
