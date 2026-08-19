<?php

namespace oihana\files\archive\tar;

/**
 * The system `tar` this library is willing to use, or null.
 *
 * `PharData` writes tar archives in pure PHP, and on anything but a toy tree it is not viable:
 * measured against GNU tar on the same 96 MB / 7 554-file directory, producing the same 17 MB
 * archive, it took **317 seconds against 1.63** — and the gap widens with size, because it
 * writes the tar and then reads the whole thing back to compress it. It also refuses any path
 * component longer than 100 bytes, the ustar limit, which a single file of a stock WordPress
 * plugin set is enough to hit.
 *
 * So the work is handed to the system binary when one is there. Which one matters:
 *
 * - **GNU tar** stores names as raw bytes, exactly as `PharData` does. Verified on a tree of
 *   accented, CJK, quoted and spaced names, plus a symlink and an empty directory: the entry
 *   lists are identical.
 * - **bsdtar**, which is what macOS ships as `/usr/bin/tar`, converts filenames to Unicode NFD.
 *   `été.txt` becomes `e´te´.txt` inside the archive. An archive written on a Mac and restored
 *   on a server would carry different names than the originals — for a site with accented
 *   media, different URLs. Speed is not worth that, and a Mac is a development machine rather
 *   than a backup target.
 * - **BusyBox tar** is a reduced implementation whose fidelity has not been measured here.
 *
 * Hence the rule: the binary is used when it identifies itself as GNU tar, and `PharData`
 * carries the rest. Slower and identical beats faster and subtly different.
 *
 * `OIHANA_TAR_BINARY` overrides the search: a path to use one in particular, or an empty value
 * to force the `PharData` path — which is how the test suite exercises both engines on the
 * same fixtures.
 *
 * @param bool $refresh Whether to look again instead of reusing the previous answer.
 *
 * @return string|null The binary to run, or null when the archive is to be built in PHP.
 *
 * @example
 * ```php
 * $binary = tarBinary() ;
 *
 * echo $binary === null
 *     ? 'archives are built in PHP, which is slow on large trees'
 *     : sprintf( 'archives are built by %s' , $binary ) ;
 * ```
 *
 * @package oihana\files\archive\tar
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.3.0
 */
function tarBinary( bool $refresh = false ) :?string
{
    static $resolved = false ;
    static $binary   = null ;

    if ( $resolved && !$refresh )
    {
        return $binary ;
    }

    $resolved = true ;
    $binary   = null ;

    $override = getenv( 'OIHANA_TAR_BINARY' ) ;

    if ( $override !== false )
    {
        // Declared and empty means "do not use one" — the seam the tests use to run the whole
        // suite through PharData on a machine that has a perfectly good tar.

        $binary = $override === '' ? null : ( tarBinaryIsUsable( $override ) ? $override : null ) ;

        return $binary ;
    }

    foreach ( [ '/usr/bin/tar' , '/bin/tar' , '/usr/local/bin/tar' , '/opt/homebrew/bin/gtar' , '/usr/bin/gtar' ] as $candidate )
    {
        if ( tarBinaryIsUsable( $candidate ) )
        {
            $binary = $candidate ;
            break ;
        }
    }

    return $binary ;
}

/**
 * Whether a candidate is an executable GNU tar.
 *
 * Asked by running it rather than by trusting its name: `/usr/bin/tar` is GNU tar on Linux and
 * bsdtar on macOS, and the two do not treat filenames the same way.
 *
 * @param string $candidate The path to test.
 *
 * @return bool True when the binary runs and identifies itself as GNU tar.
 *
 * @package oihana\files\archive\tar
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.3.0
 */
function tarBinaryIsUsable( string $candidate ) :bool
{
    if ( $candidate === '' || !is_file( $candidate ) || !is_executable( $candidate ) )
    {
        return false ;
    }

    $descriptors = [ 1 => [ 'pipe' , 'w' ] , 2 => [ 'pipe' , 'w' ] ] ;

    $process = @proc_open
    (
        [ $candidate , '--version' ] ,
        $descriptors ,
        $pipes ,
        null ,
        [ 'LC_ALL' => 'C' ]
    ) ;

    if ( !is_resource( $process ) )
    {
        return false ;
    }

    $output = stream_get_contents( $pipes[ 1 ] ) ?: '' ;

    fclose( $pipes[ 1 ] ) ;
    fclose( $pipes[ 2 ] ) ;

    return proc_close( $process ) === 0 && str_contains( $output , 'GNU tar' ) ;
}
