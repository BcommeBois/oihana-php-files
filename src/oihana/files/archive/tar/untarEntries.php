<?php

namespace oihana\files\archive\tar ;

use RuntimeException;

/**
 * Lists what an archive holds, without extracting it.
 *
 * The listing pass of {@see untar()}. It exists because three of that function's
 * guarantees — a dry run, refusing to overwrite, and the decompression-bomb cap — all
 * need to know the contents **before** the first byte is written, and `tar` will not
 * tell and extract in one go. The pass streams: `tar` reads headers and reports, so a
 * 2 GB archive costs a listing, not a copy.
 *
 * It runs `tar -tvf` with three flags that are not decoration:
 *
 * - `--quoting-style=literal`, because the default style escapes unusual characters, so
 *   a name would come back spelled differently from the name on disk;
 * - `--numeric-owner`, so the owner column is `501/0` and cannot contain a space, which
 *   is what lets the name be read as *everything after the time*;
 * - `LC_ALL=C`, so the date column keeps the one shape this parses.
 *
 * **A line that does not parse raises.** It would be easier to skip it, and that is
 * exactly what must not happen: a `maxExtractedSize` computed from a listing with a
 * hole in it is a cap that under-counts, which is to say a guard that no longer guards.
 * The known case is a filename containing a newline, which no line-based listing can
 * represent — rare, legal on POSIX, and reported rather than ignored.
 *
 * @param string $binary  The GNU tar to run, from {@see tarBinary()}.
 * @param string $tarFile Path to the archive.
 *
 * @return array<int, array{name: string, size: int, directory: bool}> One entry per
 *         member, in archive order. A link's name stops before its ` -> target`.
 *
 * @throws RuntimeException If `tar` cannot read the archive, or prints a line this
 *                          cannot parse.
 *
 * @example
 * ```php
 * use function oihana\files\archive\tar\tarBinary;
 * use function oihana\files\archive\tar\untarEntries;
 *
 * foreach ( untarEntries( tarBinary() , '/backups/site.tar.gz' ) as $entry )
 * {
 *     printf( "%-60s %d\n" , $entry[ 'name' ] , $entry[ 'size' ] ) ;
 * }
 * ```
 *
 * @package oihana\files\archive\tar
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.5.0
 */
function untarEntries( string $binary , string $tarFile ) :array
{
    $descriptors = [ 1 => [ 'pipe' , 'w' ] , 2 => [ 'pipe' , 'w' ] ] ;

    $process = @proc_open
    (
        [ $binary , '-tvf' , $tarFile , '--quoting-style=literal' , '--numeric-owner' ] ,
        $descriptors ,
        $pipes ,
        null ,
        tarProcessEnvironment()
    ) ;

    // Reachable only if the process table is exhausted; tarBinary() has already run this
    // very binary to identify it.
    // @codeCoverageIgnoreStart
    if ( !is_resource( $process ) )
    {
        throw new RuntimeException( sprintf( 'untarEntries() failed, could not run %s.' , $binary ) ) ;
    }
    // @codeCoverageIgnoreEnd

    $output = stream_get_contents( $pipes[ 1 ] ) ?: '' ;
    $errors = stream_get_contents( $pipes[ 2 ] ) ?: '' ;

    fclose( $pipes[ 1 ] ) ;
    fclose( $pipes[ 2 ] ) ;

    if ( proc_close( $process ) !== 0 )
    {
        throw new RuntimeException( sprintf
        (
            'untarEntries() failed, %s could not list %s: %s' ,
            $binary ,
            $tarFile ,
            trim( $errors ) === '' ? 'no error reported' : trim( $errors )
        )) ;
    }

    $entries = [] ;

    foreach ( explode( "\n" , $output ) as $line )
    {
        if ( $line === '' )
        {
            continue ;
        }

        if ( preg_match( '#^(.)[rwxsStT-]{9}\s+\d+/\d+\s+(\d+)\s+\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}(?::\d{2})?\s+(.+)$#' , $line , $matches ) !== 1 )
        {
            throw new RuntimeException( sprintf
            (
                'untarEntries() failed, this line of the listing of %s could not be read, so the contents cannot be accounted for: %s' ,
                $tarFile ,
                $line
            )) ;
        }

        $name = $matches[ 3 ] ;

        if ( $matches[ 1 ] === 'l' || $matches[ 1 ] === 'h' )
        {
            $arrow = strpos( $name , ' -> ' ) ;

            if ( $arrow !== false )
            {
                $name = substr( $name , 0 , $arrow ) ;
            }
        }

        $entries[] =
        [
            'name'      => $name ,
            'size'      => (int) $matches[ 2 ] ,
            'directory' => $matches[ 1 ] === 'd' ,
        ] ;
    }

    return $entries ;
}
