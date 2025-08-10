<?php

namespace oihana\files ;

use oihana\files\exceptions\DirectoryException;
use function oihana\files\path\joinPaths;

/**
 * Checks if a directory contains at least one file,
 * or only files if strict mode is enabled.
 *
 * @param string|null $dir    The path to the directory to check.
 *                            Must be a valid readable directory.
 * @param bool        $strict If true, the function returns true only if
 *                            the directory contains *only* files (no directories or other items).
 *                            Defaults to false.
 *
 * @return bool Returns true if the directory contains at least one file,
 *              or if in strict mode, only files.
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
 *     // Check if directory contains at least one file
 *     if ( hasFiles( $dir ) )
 *     {
 *         echo "Directory contains at least one file.\n";
 *     }
 *     else
 *     {
 *         echo "No files found.\n";
 *     }
 *
 *     // Check if directory contains only files (strict mode)
 *     if (hasFiles($dir, true))
 *     {
 *         echo "Directory contains only files.\n";
 *     }
 *     else
 *     {
 *         echo "Directory contains directories or other items besides files.\n";
 *     }
 * }
 * catch (DirectoryException $e)
 * {
 *     echo "Error: " . $e->getMessage() . "\n";
 * }
 * ```
 */
function hasFiles( ?string $dir , bool $strict = false ): bool
{
    assertDirectory($dir);

    $items  = scandir($dir);
    $hasFile = false;

    foreach ($items as $item)
    {
        if ($item === '.' || $item === '..')
        {
            continue;
        }

        $fullPath = joinPaths($dir, $item);

        if ( is_file( $fullPath ) )
        {
            $hasFile = true;
        }
        else if ( $strict )
        {
            return false;
        }
    }

    return $hasFile;
}