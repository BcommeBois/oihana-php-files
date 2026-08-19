<?php

namespace oihana\files\archive\tar;

use RuntimeException;

use oihana\files\enums\CompressionType;

/**
 * Builds an archive with the system `tar`.
 *
 * The entry names are computed in PHP by {@see tarEntries()} and handed over verbatim, with
 * `--no-recursion`, so `tar` walks nothing and decides nothing: it writes exactly the list it
 * is given, under exactly the names `PharData` would have used. That is what keeps archives
 * written by either engine interchangeable.
 *
 * The list travels on standard input, NUL-separated. Passed as arguments it would break on any
 * real site — a stock WordPress install is sixteen thousand paths, far past `ARG_MAX` — and
 * NUL separation is the only form that survives newlines and quotes in filenames.
 *
 * @param string                                                                       $binary      The tar to run.
 * @param array<int, array{base: string, name: string, directory: bool, path: string}> $entries     What to archive.
 * @param string                                                                       $finalPath   Where the archive goes.
 * @param string                                                                       $compression A {@see CompressionType} value.
 *
 * @throws RuntimeException If tar cannot be started, or refuses the archive.
 *
 * @package oihana\files\archive\tar
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.3.0
 */
function tarWithBinary( string $binary , array $entries , string $finalPath , string $compression ) :void
{
    $grouped = tarEntriesByBase( $entries ) ;

    $base  = array_key_first( $grouped ) ;
    $names = $grouped[ $base ] ;

    // Order matters, and GNU tar says so out loud: `--directory` is positional and applies
    // only to the names that come after it. Placed after `--files-from` it is ignored, and
    // tar then looks for every entry relative to the current working directory instead.

    $arguments = [ $binary , '--create' ] ;

    $flag = tarCompressionFlag( $compression ) ;

    if ( $flag !== null )
    {
        $arguments[] = $flag ;
    }

    array_push
    (
        $arguments ,
        '--file'        , $finalPath ,
        '--directory'   , $base ,
        '--no-recursion' ,
        '--null' ,
        '--files-from'  , '-' ,
    ) ;

    $descriptors = [ 0 => [ 'pipe' , 'r' ] , 1 => [ 'pipe' , 'w' ] , 2 => [ 'pipe' , 'w' ] ] ;

    $process = @proc_open( $arguments , $descriptors , $pipes , null , [ 'LC_ALL' => 'C' ] ) ;

    if ( !is_resource( $process ) )
    {
        throw new RuntimeException( sprintf( 'Unable to run %s.' , $binary ) ) ;
    }

    foreach ( $names as $name )
    {
        fwrite( $pipes[ 0 ] , $name . "\0" ) ;
    }

    fclose( $pipes[ 0 ] ) ;

    stream_get_contents( $pipes[ 1 ] ) ;

    $error = stream_get_contents( $pipes[ 2 ] ) ?: '' ;

    fclose( $pipes[ 1 ] ) ;
    fclose( $pipes[ 2 ] ) ;

    $code = proc_close( $process ) ;

    if ( $code !== 0 )
    {
        // Deliberately not falling back to PharData here. A tar that is present and fails is
        // reporting something PharData would meet as well — a full disk, a path it may not
        // read — and retrying in PHP would spend minutes to reach the same wall, with the
        // original reason discarded.

        if ( file_exists( $finalPath ) )
        {
            unlink( $finalPath ) ;
        }

        throw new RuntimeException
        (
            sprintf( '%s exited with %d: %s' , $binary , $code , trim( $error ) !== '' ? trim( $error ) : '(no output)' )
        ) ;
    }
}

/**
 * Whether the system tar can produce this compression, and with which flag.
 *
 * @param string $compression A {@see CompressionType} value.
 *
 * @return string|null The flag, null for an uncompressed archive.
 *
 * @package oihana\files\archive\tar
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.3.0
 */
function tarCompressionFlag( string $compression ) :?string
{
    return match( $compression )
    {
        CompressionType::GZIP  => '--gzip'  ,
        CompressionType::BZIP2 => '--bzip2' ,
        CompressionType::XZ    => '--xz'    ,
        CompressionType::LZMA  => '--lzma'  ,
        default                => null      ,
    } ;
}

/**
 * Whether the compressor `tar` would shell out to is installed.
 *
 * `tar` does not compress: it pipes through `gzip`, `bzip2`, `xz`. `PharData` does it in PHP,
 * through the bundled extensions — so a machine with `ext-bz2` and no `bzip2` program used to
 * produce a bzip2 archive and would stop. Asked before the engine is chosen, so that host
 * keeps the engine that works for it instead of receiving an error where it had a file.
 *
 * @param string $compression A {@see CompressionType} value.
 *
 * @return bool True when nothing external is needed, or when what is needed is there.
 *
 * @package oihana\files\archive\tar
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.3.0
 */
function tarCompressorExists( string $compression ) :bool
{
    $program = match( $compression )
    {
        CompressionType::GZIP  => 'gzip'  ,
        CompressionType::BZIP2 => 'bzip2' ,
        CompressionType::XZ    => 'xz'    ,
        CompressionType::LZMA  => 'lzma'  ,
        default                => null    ,
    } ;

    if ( $program === null )
    {
        return true ;
    }

    foreach ( explode( PATH_SEPARATOR , (string) getenv( 'PATH' ) ) as $directory )
    {
        if ( $directory !== '' && is_executable( $directory . DIRECTORY_SEPARATOR . $program ) )
        {
            return true ;
        }
    }

    return false ;
}

/**
 * Whether the binary engine can take this archive on.
 *
 * It takes the ones it can reproduce exactly, and leaves the rest to `PharData` — decided
 * before anything is written, never after something has failed.
 *
 * Archiving several paths that do not share a parent needs one `tar` invocation per parent,
 * and an archive cannot be appended to once compressed. That case is rare — it means passing
 * unrelated paths with no `preserveRoot` — and PharData already handles it correctly, so it
 * stays there rather than justifying a second, less-travelled code path.
 *
 * @param array<int, array{base: string, name: string, directory: bool, path: string}> $entries
 * @param string $compression
 *
 * @return bool
 *
 * @package oihana\files\archive\tar
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.3.0
 */
function tarBinaryHandles( array $entries , string $compression ) :bool
{
    if ( count( $entries ) === 0 )
    {
        return false ;
    }

    if ( $compression !== CompressionType::NONE && tarCompressionFlag( $compression ) === null )
    {
        return false ;
    }

    if ( !tarCompressorExists( $compression ) )
    {
        return false ;
    }

    return count( tarEntriesByBase( $entries ) ) === 1 ;
}
