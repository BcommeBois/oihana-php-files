<?php

namespace oihana\files ;

use oihana\files\exceptions\DirectoryException;
use oihana\files\exceptions\FileException;

/**
 * Moves (or renames) a single file to a destination path.
 *
 * Shares the destination semantics of {@see copyFile()}:
 *
 * - the source is validated with {@see assertFile()} (must exist and be readable);
 * - if `$destination` is an **existing directory**, the file is moved **inside** it,
 *   keeping the source basename;
 * - when `$overwrite` is `false`, an existing destination raises a {@see FileException};
 * - the destination's parent directory is created on demand when `$createDirectory` is `true`.
 *
 * The move is performed with `rename()` (atomic on the same filesystem). When the
 * source and destination live on **different filesystems**, `rename()` cannot span
 * devices, so the function transparently falls back to {@see copyFile()} + {@see deleteFile()}.
 *
 * @param string $source          Path to the source file to move.
 * @param string $destination     Destination file path, or an existing directory to move into.
 * @param bool   $overwrite       Whether to overwrite an existing destination file (default: `true`).
 * @param bool   $createDirectory Whether to create the destination directory if missing (default: `true`).
 *
 * @return bool Returns `true` on success.
 *
 * @throws FileException      If the source is invalid, or the destination exists and `$overwrite` is `false`.
 * @throws DirectoryException If the destination directory is missing and `$createDirectory` is `false`,
 *                            or cannot be created.
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.2.0
 *
 * @example
 * ```php
 * use function oihana\files\moveFile;
 *
 * moveFile( '/tmp/upload.tmp' , '/data/final.pdf' ) ;  // move + rename
 * moveFile( '/data/final.pdf' , '/archive' ) ;         // into a directory
 * ```
 */
function moveFile( string $source , string $destination , bool $overwrite = true , bool $createDirectory = true ): bool
{
    assertFile( $source ) ;

    if ( is_dir( $destination ) )
    {
        $destination = rtrim( $destination , DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR . basename( $source ) ;
    }

    if ( !$overwrite && is_file( $destination ) && realpath( $source ) !== realpath( $destination ) )
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

    if ( @rename( $source , $destination ) )
    {
        return true ;
    }

    // rename() cannot move across filesystems (EXDEV); fall back to copy + delete.
    // @codeCoverageIgnoreStart
    copyFile( $source , $destination , $overwrite , $createDirectory ) ;
    deleteFile( $source ) ;
    return true ;
    // @codeCoverageIgnoreEnd
}
