<?php

namespace oihana\files\archive\tar ;

/**
 * Tells what a copy made by {@see tarCopyScript()} amounts to.
 *
 * Exit code 1 is accepted when, and only when, **every** line tar wrote says that a file moved
 * while it was read. GNU tar has three forms of it:
 *
 * - `tar: <path>: file changed as we read it` — a file written to during the copy;
 * - `tar: <path>: File removed before we read it` — a file deleted between the listing and the
 *   reading, a cache entry for instance;
 * - `tar: <path>: File shrank by <n> bytes; padding with zeros` — a file cut short while it was
 *   read, a log being truncated. Its copy is completed with zeros.
 *
 * A copy taken from a directory in use is never a frozen picture of it; these three say which
 * files were caught in motion, and they are named so a caller can report them. One other line,
 * and the copy fails: tar exits 1 for nothing else, and a message this function has not been
 * taught is not one to wave through.
 *
 * @param int    $status What the script exited with: 0, 1 or 2 from the reading tar, 3 when the
 *                       writing one failed.
 * @param string $error  What the two tar wrote on standard error.
 *
 * @return array{ok: bool, moved: list<string>, reason: string} Whether the copy stands, the paths
 *         that moved while they were read, and why the copy failed when it did.
 *
 * @example
 * ```php
 * use function oihana\files\archive\tar\tarCopyOutcome;
 *
 * tarCopyOutcome( 0 , '' ) ;
 * // [ 'ok' => true , 'moved' => [] , 'reason' => '' ]
 *
 * tarCopyOutcome( 1 , "tar: htdocs/debug.log: file changed as we read it\n" ) ;
 * // [ 'ok' => true , 'moved' => [ 'htdocs/debug.log' ] , 'reason' => '' ]
 *
 * tarCopyOutcome( 3 , "tar: htdocs/a.jpg: Cannot write: No space left on device\n" ) ;
 * // [ 'ok' => false , 'moved' => [] , 'reason' => 'the copy could not be written to its destination: tar: htdocs/a.jpg: …' ]
 * ```
 *
 * @package oihana\files\archive\tar
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.7.0
 */
function tarCopyOutcome( int $status , string $error ) :array
{
    $lines = array_values( array_filter( array_map( 'trim' , explode( "\n" , $error ) ) , static fn( string $line ) :bool => $line !== '' ) ) ;

    $said = implode( ' / ' , $lines ) ;

    if( $status === 0 )
    {
        return [ 'ok' => true , 'moved' => [] , 'reason' => '' ] ;
    }

    if( $status === 3 )
    {
        return
        [
            'ok'     => false ,
            'moved'  => [] ,
            'reason' => 'the copy could not be written to its destination'
                      . ( $said !== '' ? ': ' . $said : '' ) ,
        ] ;
    }

    if( $status !== 1 )
    {
        return
        [
            'ok'     => false ,
            'moved'  => [] ,
            'reason' => $said !== '' ? $said : sprintf( 'tar exited with %d and said nothing' , $status ) ,
        ] ;
    }

    $moved = [] ;

    foreach( $lines as $line )
    {
        if( preg_match( '/^tar: (.+): (?:file changed as we read it|File removed before we read it|File shrank by \d+ bytes; padding with zeros)$/' , $line , $matches ) !== 1 )
        {
            return
            [
                'ok'     => false ,
                'moved'  => [] ,
                'reason' => $said ,
            ] ;
        }

        $moved[] = $matches[ 1 ] ;
    }

    if( $moved === [] )
    {
        return [ 'ok' => false , 'moved' => [] , 'reason' => 'tar exited with 1 and said nothing' ] ;
    }

    return [ 'ok' => true , 'moved' => $moved , 'reason' => '' ] ;
}
