<?php

namespace oihana\files\archive\tar ;

/**
 * Writes the sentence that names the files tar saw move while it copied them.
 *
 * The `moved` list of {@see tarCopyOutcome()}, put in words for a terminal, a log or a mail. A
 * terminal can take every file; a mail or a report should take a few: a cache folder that keeps
 * changing must not make a message of a thousand lines. So a limit names that many at most, and
 * says how many it left out. The count is always of every file.
 *
 * @param array<string> $moved The files, as tar named them.
 * @param string        $what  What was being copied, e.g. `content` or `the site root`.
 * @param int|null      $limit How many files to name at most, one at least; null names them all.
 *
 * @return string The sentence; an empty string when no file moved.
 *
 * @example
 * ```php
 * use function oihana\files\archive\tar\tarCopyMovedLine;
 *
 * tarCopyMovedLine( [ 'content/a.log' ] , 'content' ) ;
 * // '1 file changed while content was copied, and was copied as found: content/a.log'
 *
 * tarCopyMovedLine( [ 'a' , 'b' , 'c' , 'd' ] , 'content' , 2 ) ;
 * // '4 files changed while content was copied, and were copied as found: a, b … and 2 more'
 * ```
 *
 * @package oihana\files\archive\tar
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.7.0
 */
function tarCopyMovedLine( array $moved , string $what , ?int $limit = null ) :string
{
    $count = count( $moved ) ;

    if ( $count === 0 )
    {
        return '' ;
    }

    $named = $limit !== null ? array_slice( $moved , 0 , max( 1 , $limit ) ) : $moved ;
    $rest  = $count - count( $named ) ;

    return sprintf
    (
        '%d file%s changed while %s was copied, and %s copied as found: %s%s' ,
        $count ,
        $count > 1 ? 's' : '' ,
        $what ,
        $count > 1 ? 'were' : 'was' ,
        implode( ', ' , $named ) ,
        $rest > 0 ? sprintf( ' … and %d more' , $rest ) : ''
    ) ;
}
