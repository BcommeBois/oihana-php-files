<?php

namespace oihana\files\path ;

/**
 * Resolves a path's parent and leaves its last segment as it stands.
 *
 * `realpath()` answers for the whole path, which is too much when the last segment is the thing
 * being judged: a symbolic link would be replaced by what it points at, and a path that does not
 * exist would answer `false`. This resolves everything above the last segment — so a linked
 * ancestor cannot hide where a path really leads — and keeps the last segment verbatim.
 *
 * When the parent cannot be resolved, because it does not exist, the path is canonicalized
 * lexically instead: there is nothing on disk to ask.
 *
 * @param string $path The path to resolve.
 *
 * @return string The path with its parent resolved and its last segment untouched.
 *
 * @example
 * ```php
 * use function oihana\files\path\realpathParent;
 *
 * // On macOS, where /var is a link to /private/var:
 * realpathParent( '/var/folders/x/T' ) ; // '/private/var/folders/x/T'
 *
 * // The parent is the filesystem root, and the result carries one separator, not two:
 * realpathParent( '/tmp' ) ;             // '/tmp'
 *
 * // A link is kept as itself, never followed:
 * symlink( '/elsewhere' , '/tmp/pointer' ) ;
 * realpathParent( '/tmp/pointer' ) ;     // '/tmp/pointer'
 * ```
 *
 * @package oihana\files\path
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.6.0
 */
function realpathParent( string $path ): string
{
    $parent = realpath( dirname( $path ) ) ;

    if ( $parent === false )
    {
        return canonicalizePath( $path ) ;
    }

    // The parent may be the filesystem root, which already ends with a separator. Concatenating
    // without trimming it gives "//tmp", a path no comparison against "/tmp" ever matches.

    return rtrim( $parent , DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR . basename( $path ) ;
}
