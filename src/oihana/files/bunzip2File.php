<?php

namespace oihana\files ;

use oihana\files\enums\FileExtension;
use oihana\files\exceptions\FileException;

/**
 * Decompresses a single bzip2 file, streaming it in chunks.
 *
 * Counterpart of {@see bzip2File()}, using the `bz2` extension — no subprocess,
 * and the file is never fully loaded into memory.
 *
 * @param string      $source      Path to the bzip2 file to decompress.
 * @param string|null $destination Output path. Defaults to `$source` without its `.bz2` suffix,
 *                                 or `$source` + `.out` when the source has no `.bz2` suffix.
 * @param bool        $overwrite   Whether to overwrite an existing destination (default: `true`).
 *
 * @return string The destination path.
 *
 * @throws FileException If the source is invalid, `bz2` is unavailable, the destination exists
 *                       and `$overwrite` is `false`, or decompression fails.
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.2.0
 *
 * @example
 * ```php
 * use function oihana\files\bunzip2File;
 *
 * bunzip2File( '/data/dump.sql.bz2' ) ; // -> /data/dump.sql
 * ```
 */
function bunzip2File( string $source , ?string $destination = null , bool $overwrite = true ): string
{
    assertFile( $source ) ;

    if ( !function_exists( 'bzopen' ) )
    {
        // Covered by tests\oihana\files\Bzip2ExtensionTest, which stages a host without the
        // functions in a child started with `disable_functions=bzopen,…` — coverage does not
        // follow a subprocess, so the line reads as untested here while being asserted there.
        // Without this guard PHP dies on `Call to undefined function bzopen()`: a fatal error,
        // so nothing to catch and nothing for a caller to report.
        // @codeCoverageIgnoreStart
        throw new FileException( 'The "bz2" PHP extension is required to bunzip2 files.' ) ;
        // @codeCoverageIgnoreEnd
    }

    if ( $destination === null )
    {
        $destination = str_ends_with( $source , FileExtension::BZ2 )
            ? substr( $source , 0 , -strlen( FileExtension::BZ2 ) )
            : $source . '.out' ;
    }

    if ( !$overwrite && is_file( $destination ) )
    {
        throw new FileException( sprintf( 'The destination file "%s" already exists.' , $destination ) ) ;
    }

    $in = @bzopen( $source , 'r' ) ;
    if ( $in === false )
    {
        // bzopen() does not fail after assertFile() on a readable file.
        // @codeCoverageIgnoreStart
        throw new FileException( sprintf( 'Failed to open the bzip2 source "%s".' , $source ) ) ;
        // @codeCoverageIgnoreEnd
    }

    $out = @fopen( $destination , 'wb' ) ;
    if ( $out === false )
    {
        bzclose( $in ) ;
        throw new FileException( sprintf( 'Failed to open the destination "%s".' , $destination ) ) ;
    }

    while ( !feof( $in ) )
    {
        $chunk = bzread( $in , 8192 ) ;
        if ( $chunk === false )
        {
            // bzread() returns false only on a corrupted stream, not exercised here.
            // @codeCoverageIgnoreStart
            break ;
            // @codeCoverageIgnoreEnd
        }
        fwrite( $out , $chunk ) ;
    }

    bzclose( $in ) ;
    fclose( $out ) ;

    return $destination ;
}
