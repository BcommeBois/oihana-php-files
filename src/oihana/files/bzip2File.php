<?php

namespace oihana\files ;

use oihana\files\enums\FileExtension;
use oihana\files\exceptions\FileException;

/**
 * Compresses a single file with bzip2, streaming it in chunks.
 *
 * Standalone bzip2 compression outside of tar archives, using the `bz2`
 * extension — no subprocess, and the file is never fully loaded into memory.
 *
 * Unlike {@see gzipFile()}, no compression level is exposed: `bzopen()` does not
 * accept one in streaming mode.
 *
 * @param string      $source      Path to the file to compress.
 * @param string|null $destination Output path. Defaults to `$source` + `.bz2`.
 * @param bool        $overwrite   Whether to overwrite an existing destination (default: `true`).
 *
 * @return string The destination path.
 *
 * @throws FileException If the source is invalid, `bz2` is unavailable, the destination exists
 *                       and `$overwrite` is `false`, or compression fails.
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.2.0
 *
 * @example
 * ```php
 * use function oihana\files\bzip2File;
 *
 * bzip2File( '/data/dump.sql' ) ; // -> /data/dump.sql.bz2
 * ```
 */
function bzip2File( string $source , ?string $destination = null , bool $overwrite = true ): string
{
    assertFile( $source ) ;

    if ( !function_exists( 'bzopen' ) )
    {
        // ext-bz2 is present in the test environment.
        // @codeCoverageIgnoreStart
        throw new FileException( 'The "bz2" PHP extension is required to bzip2 files.' ) ;
        // @codeCoverageIgnoreEnd
    }

    $destination ??= $source . FileExtension::BZ2 ;

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

    $out = @bzopen( $destination , 'w' ) ;
    if ( $out === false )
    {
        fclose( $in ) ;
        throw new FileException( sprintf( 'Failed to open the bzip2 destination "%s".' , $destination ) ) ;
    }

    while ( !feof( $in ) )
    {
        bzwrite( $out , fread( $in , 8192 ) ) ;
    }

    fclose( $in ) ;
    bzclose( $out ) ;

    return $destination ;
}
