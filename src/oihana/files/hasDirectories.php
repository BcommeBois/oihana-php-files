<?php

namespace oihana\files ;

use oihana\files\exceptions\DirectoryException;
use function oihana\files\path\joinPaths;

/**
 * Checks if a directory contains at least one subdirectory,
 * or only subdirectories if strict mode is enabled.
 *
 * @param string|null $dir    The path to the directory to check.
 *                            Must be a valid readable directory.
 * @param bool        $strict If true, the function returns true only if
 *                            the directory contains *only* subdirectories (no files or other items).
 *                            Defaults to false.
 *
 * @return bool Returns true if the directory contains at least one subdirectory,
 *              or if in strict mode, only subdirectories.
 *
 * @throws DirectoryException If the path is null, empty, not a directory,
 *                            or does not meet the readability/writability requirements
 *                            as checked by assertDirectory().
 *
 * @example
 * ```php
 * try
 * {
 *     $dir = '/path/to/directory';
 *
 *     // Check if directory contains at least one subdirectory
 *     if ( hasDirectories($dir) )
 *     {
 *         echo "Directory contains at least one subdirectory.\n";
 *     }
 *     else
 *     {
 *         echo "No subdirectories found.\n";
 *     }
 *
 *     // Check if directory contains only subdirectories (strict mode)
 *     if ( hasDirectories( $dir , true ) )
 *     {
 *         echo "Directory contains only subdirectories.\n";
 *     }
 *     else
 *     {
 *         echo "Directory contains files or other items besides subdirectories.\n";
 *     }
 * }
 * catch (DirectoryException $e)
 * {
 *     echo "Error: " . $e->getMessage() . "\n";
 * }
 * ```
 *
 * @package oihana\files\path
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
function hasDirectories( ?string $dir , bool $strict = false ): bool
{
    assertDirectory( $dir ) ;

    $items  = scandir( $dir ) ;
    $hasDir = false;

    foreach ( $items as $item )
    {
        if ( $item === '.' || $item === '..' )
        {
            continue;
        }

        $fullPath = joinPaths( $dir ,  $item ) ;

        if ( is_dir( $fullPath ) )
        {
            $hasDir = true ;
        }
        elseif ( $strict )
        {
            return false;
        }
    }

    return $hasDir ;
}