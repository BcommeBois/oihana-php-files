<?php

namespace oihana\files ;

use oihana\files\exceptions\DirectoryException;
use oihana\files\exceptions\FileException;

/**
 * Copies a single file to a destination path.
 *
 * Unlike {@see copyFilteredFiles()} (which mirrors a whole directory tree), this
 * helper copies **one** file with explicit, typed error handling:
 *
 * - the source is validated with {@see assertFile()} (must exist and be readable);
 * - if `$destination` is an **existing directory**, the file is copied **inside** it,
 *   keeping the source basename (the `cp source dir/` convention);
 * - copying a file onto itself is refused (it would truncate the source);
 * - when `$overwrite` is `false`, an existing destination raises a {@see FileException};
 * - the destination's parent directory is created on demand when `$createDirectory` is `true`.
 *
 * @param string $source          Path to the source file to copy.
 * @param string $destination     Destination file path, or an existing directory to copy into.
 * @param bool   $overwrite       Whether to overwrite an existing destination file (default: `true`).
 * @param bool   $createDirectory Whether to create the destination directory if missing (default: `true`).
 *
 * @return bool Returns `true` on success.
 *
 * @throws FileException      If the source is invalid, source and destination are the same file,
 *                            the destination exists and `$overwrite` is `false`, or the copy fails.
 * @throws DirectoryException If the destination directory is missing and `$createDirectory` is `false`,
 *                            or cannot be created.
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.2.0
 *
 * @example
 * ```php
 * use function oihana\files\copyFile;
 *
 * copyFile( '/data/report.pdf' , '/backup/report.pdf' ) ;          // explicit target
 * copyFile( '/data/report.pdf' , '/backup' ) ;                     // into a directory
 * copyFile( '/data/report.pdf' , '/backup/report.pdf' , false ) ;  // throws if it already exists
 * ```
 */
function copyFile( string $source , string $destination , bool $overwrite = true , bool $createDirectory = true ): bool
{
    assertFile( $source ) ;

    if ( is_dir( $destination ) )
    {
        $destination = rtrim( $destination , DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR . basename( $source ) ;
    }

    if ( realpath( $source ) === realpath( $destination ) )
    {
        throw new FileException( sprintf( 'The source and destination refer to the same file "%s".' , $source ) ) ;
    }

    if ( !$overwrite && is_file( $destination ) )
    {
        throw new FileException( sprintf( 'The destination file "%s" already exists.' , $destination ) ) ;
    }

    $directory = dirname( $destination ) ;
    if ( !is_dir( $directory ) )
    {
        if ( !$createDirectory )
        {
            throw new DirectoryException( sprintf( 'The destination directory "%s" does not exist.' , $directory ) ) ;
        }
        makeDirectory( $directory ) ;
    }

    if ( !@copy( $source , $destination ) )
    {
        throw new FileException( sprintf( 'Failed to copy "%s" to "%s".' , $source , $destination ) ) ;
    }

    return true ;
}
