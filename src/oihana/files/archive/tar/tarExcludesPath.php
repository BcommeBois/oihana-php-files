<?php

namespace oihana\files\archive\tar ;

/**
 * Tells whether GNU tar's `--exclude` patterns would leave a path out of an archive.
 *
 * An archive made with `tar --exclude=<pattern> …` is missing two kinds of things compared with
 * the tree it was made from: what was added since, and what a pattern left out on purpose. A
 * caller that compares the two — to restore a tree to its archive, for instance — has to tell
 * them apart, or it removes what was never meant to be archived.
 *
 * This reads the patterns the way GNU tar reads them by default:
 *
 * - **unanchored**: a pattern may match from any `/` of the member name, so `cache` also
 *   matches `app/cache` ;
 * - **leading directory**: a pattern matching a directory leaves out everything under it, so
 *   `cache` matches `app/cache/a.bin` ;
 * - **wildcards cross a slash**: `*` matches `/` too, as {@see fnmatch()} does with no flags.
 *
 * Leading and trailing slashes of the path and the patterns are ignored, and an empty pattern
 * matches nothing. Erring wide is the safe direction for the usual caller: a path taken for
 * excluded is kept, never removed.
 *
 * @param string        $path     A path relative to the directory tar was run from,
 *                                e.g. `content/cache/a.bin`.
 * @param array<string> $patterns The `--exclude` patterns.
 *
 * @return bool True when one of the patterns leaves the path out.
 *
 * @see https://www.gnu.org/software/tar/manual/html_node/controlling-pattern_002dmatching.html
 *
 * @example
 * ```php
 * use function oihana\files\archive\tar\tarExcludesPath;
 *
 * tarExcludesPath( 'content/cache/a.bin' , [ 'cache' ] ) ;         // true  (unanchored, leading directory)
 * tarExcludesPath( 'content/logs/a.log'  , [ 'content/*.log' ] ) ; // true  (* crosses the slash)
 * tarExcludesPath( 'content/cached.txt'  , [ 'cache' ] ) ;         // false (a segment, not a prefix)
 * ```
 *
 * @package oihana\files\archive\tar
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.7.0
 */
function tarExcludesPath( string $path , array $patterns ) :bool
{
    $parts = explode( '/' , trim( $path , '/' ) ) ;
    $count = count( $parts ) ;

    foreach ( $patterns as $pattern )
    {
        $pattern = trim( (string) $pattern , '/' ) ;

        if ( $pattern === '' )
        {
            continue ;
        }

        // Every run of whole segments: any start (unanchored), any end (leading directory).

        for ( $start = 0 ; $start < $count ; $start++ )
        {
            for ( $end = $start + 1 ; $end <= $count ; $end++ )
            {
                if ( fnmatch( $pattern , implode( '/' , array_slice( $parts , $start , $end - $start ) ) ) )
                {
                    return true ;
                }
            }
        }
    }

    return false ;
}
