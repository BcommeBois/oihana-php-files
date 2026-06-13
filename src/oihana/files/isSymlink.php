<?php

namespace oihana\files ;

/**
 * Tells whether a path is a symbolic link.
 *
 * Thin wrapper around `is_link()`: returns `false` (never throws) for a regular
 * file, a directory, or a non-existent path.
 *
 * @param string $path The path to test.
 *
 * @return bool `true` if `$path` is a symbolic link, `false` otherwise.
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.2.0
 *
 * @example
 * ```php
 * use function oihana\files\isSymlink;
 *
 * var_dump( isSymlink( '/var/www/current' ) ) ; // bool(true)
 * var_dump( isSymlink( '/etc/hosts' ) ) ;        // bool(false)
 * ```
 */
function isSymlink( string $path ): bool
{
    return is_link( $path ) ;
}
