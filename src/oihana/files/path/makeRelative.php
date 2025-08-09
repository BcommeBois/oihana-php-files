<?php

namespace oihana\files\path ;

use InvalidArgumentException;

/**
 * Turns an absolute path into a path relative to another absolute path.
 *
 * @param string $path     The absolute path to convert.
 * @param string $basePath The absolute path to which the conversion should be relative.
 *
 * @return string The relative path.
 *
 * @throws InvalidArgumentException If paths cannot be compared (e.g., different roots, or not absolute).
 *
 * @example
 * ```php
 * // --- Basic Unix cases ---
 *
 * // The target path is a subdirectory
 * makeRelative('/var/www/project/app', '/var/www/project');
 * // => 'app'
 *
 * // Navigating to a parent then to a sibling directory
 * makeRelative('/var/www/assets', '/var/www/project/app');
 * // => '../../assets'
 *
 * // The paths are identical
 * makeRelative('/var/www', '/var/www');
 * // => ''
 *
 * // --- Specific cases ---
 *
 * // Navigating from the root directory
 * makeRelative('/home/user/documents', '/');
 * // => 'home/user/documents'
 *
 * // Example with a Windows path
 * makeRelative('C:/Users/Test/Documents', 'C:/Users/Test/Downloads');
 * // => '../Documents'
 *
 * // Example with a URI scheme (phar)
 * makeRelative('phar:///app/src/controller', 'phar:///app/src/model');
 * // => '../controller'
 * ```
 *
 * @package oihana\files\path
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
function makeRelative( string $path , string $basePath ) :string
{
    // Prerequisite: standardize both paths for reliable comparison.

    $path     = canonicalizePath($path);
    $basePath = canonicalizePath($basePath);

    // It's impossible to determine a relative path if one of them is not absolute.

    if ( !isAbsolutePath( $path ) || !isAbsolutePath( $basePath ) )
    {
        throw new InvalidArgumentException
        (
            sprintf
            (
                'Both paths must be absolute. Provided path "%s" and base path "%s".',
                $path,
                $basePath
            )
        );
    }

    // Split paths into their root (e.g., 'C:/' or 'phar:///') and the rest of the path.
    // Assuming you have functions isAbsolutePath() and a splitPath() similar to this:
    // list($root, $relativePath) = splitPath($path);
    // For this example, we'll simulate it.

    // Simple simulation of splitPath for validation purposes
    $pathSchemePos = strpos($path, '://');
    $baseSchemePos = strpos($basePath, '://');

    $root = '/' ;

    if ( preg_match('~^[a-zA-Z]:/~', $path ) )
    {
        $root = substr($path, 0, 3 ) ;
    }

    if ($pathSchemePos !== false)
    {
        $root = substr($path, 0, $pathSchemePos + 3);
    }

    $baseRoot = '/';
    if (preg_match('~^[a-zA-Z]:/~', $basePath))
    {
        $baseRoot = substr($basePath, 0, 3);
    }

    if ($baseSchemePos !== false)
    {
        $baseRoot = substr($basePath, 0, $baseSchemePos + 3) ;
    }

    // Paths on different drives or with different schemes can't be relative.
    if ($root !== $baseRoot)
    {
        throw new InvalidArgumentException
        (
            sprintf
            (
                'The path "%s" cannot be made relative to "%s", because they have different roots ("%s" and "%s").',
                $path,
                $basePath,
                $root,
                $baseRoot
            )
        );
    }

    $relativePath     = substr( $path     , strlen( $root     ) ) ;
    $relativeBasePath = substr( $basePath , strlen( $baseRoot ) ) ;

    // If paths are identical, the relative path is empty (or "." for current dir).
    if ( $relativePath === $relativeBasePath )
    {
        return '' ;
    }

    $parts     = explode('/', trim( $relativePath     , '/' ) ) ;
    $baseParts = explode('/', trim( $relativeBasePath , '/' ) ) ;

    // Handle cases where one path is empty (e.g. root directory)
    if ( $parts === [''] )
    {
        $parts = [] ;
    }

    if ( $baseParts === [''] )
    {
        $baseParts = [] ;
    }

    $commonPartsCount = 0 ;
    foreach ( $baseParts as $index => $basePart )
    {
        if (isset($parts[$index]) && $parts[$index] === $basePart)
        {
            $commonPartsCount++ ;
        }
        else
        {
            break ;
        }
    }

    $upLevelCount = count( $baseParts ) - $commonPartsCount ;
    $dotDotPrefix = str_repeat( '../' , $upLevelCount ) ;

    $remainingParts = array_slice( $parts , $commonPartsCount ) ;

    return $dotDotPrefix . implode('/' , $remainingParts) ;
}