<?php

namespace oihana\files ;

use oihana\files\exceptions\FileException;

/**
 * Returns the target a symbolic link points to.
 *
 * @param string $link Path to the symbolic link.
 *
 * @return string The target path the symlink points to.
 *
 * @throws FileException If `$link` is not a symbolic link, or its target cannot be read.
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.2.0
 *
 * @example
 * ```php
 * use function oihana\files\readSymlink;
 *
 * echo readSymlink( '/var/www/current' ) ; // '/var/www/releases/42'
 * ```
 */
function readSymlink( string $link ): string
{
    if ( !is_link( $link ) )
    {
        throw new FileException( sprintf( 'The path "%s" is not a symbolic link.' , $link ) ) ;
    }

    $target = readlink( $link ) ;

    if ( $target === false )
    {
        // readlink() does not fail on a valid symbolic link.
        // @codeCoverageIgnoreStart
        throw new FileException( sprintf( 'Failed to read the symbolic link "%s".' , $link ) ) ;
        // @codeCoverageIgnoreEnd
    }

    return $target ;
}
