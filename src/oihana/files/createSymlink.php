<?php

namespace oihana\files ;

use oihana\files\exceptions\FileException;

/**
 * Creates a symbolic link pointing to a target.
 *
 * The target is **not** required to exist — dangling symlinks are valid and
 * supported (standard POSIX behaviour). When an entry already exists at `$link`,
 * it is replaced only if `$overwrite` is `true`.
 *
 * @param string $target    The path the symlink points to.
 * @param string $link       The symlink path to create.
 * @param bool   $overwrite Whether to replace an existing file/symlink at `$link` (default: `false`).
 *
 * @return bool Returns `true` on success.
 *
 * @throws FileException If an entry already exists at `$link` and `$overwrite` is `false`,
 *                       or if the symlink cannot be created.
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.2.0
 *
 * @example
 * ```php
 * use function oihana\files\createSymlink;
 *
 * createSymlink( '/var/www/releases/42' , '/var/www/current' , overwrite: true ) ;
 * ```
 */
function createSymlink( string $target , string $link , bool $overwrite = false ): bool
{
    if ( is_link( $link ) || file_exists( $link ) )
    {
        if ( !$overwrite )
        {
            throw new FileException( sprintf( 'An entry already exists at "%s".' , $link ) ) ;
        }

        if ( !@unlink( $link ) )
        {
            // unlink of an existing, owned symlink/file does not fail under the test runner.
            // @codeCoverageIgnoreStart
            throw new FileException( sprintf( 'Failed to replace the existing entry at "%s".' , $link ) ) ;
            // @codeCoverageIgnoreEnd
        }
    }

    if ( !@symlink( $target , $link ) )
    {
        throw new FileException( sprintf( 'Failed to create the symlink "%s" -> "%s".' , $link , $target ) ) ;
    }

    return true ;
}
