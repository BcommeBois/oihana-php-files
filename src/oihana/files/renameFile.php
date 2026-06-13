<?php

namespace oihana\files ;

use oihana\files\exceptions\DirectoryException;
use oihana\files\exceptions\FileException;

/**
 * Renames a single file.
 *
 * Semantic alias of {@see moveFile()}: renaming a file is the same operation as
 * moving it (the destination may be a new name in the same directory, a path in
 * another directory, or an existing directory to move into). See {@see moveFile()}
 * for the full destination, overwrite and cross-filesystem semantics.
 *
 * @param string $source          Path to the source file to rename.
 * @param string $destination     New file path, or an existing directory to move into.
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
 * use function oihana\files\renameFile;
 *
 * renameFile( '/data/old-name.txt' , '/data/new-name.txt' ) ;
 * ```
 */
function renameFile( string $source , string $destination , bool $overwrite = true , bool $createDirectory = true ): bool
{
    return moveFile( $source , $destination , $overwrite , $createDirectory ) ;
}
