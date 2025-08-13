<?php

namespace oihana\files\path ;

use InvalidArgumentException;
use oihana\enums\Char;

/**
 * Returns the relative path from a base path to a target path.
 *
 * Both paths must be of the same type (both absolute or both relative),
 * and must share the same root (e.g., same drive letter on Windows).
 * The result is a canonical relative path.
 *
 * @param string $path     The target path.
 * @param string $basePath The base path.
 *
 * @return string The relative path.
 *
 * @throws InvalidArgumentException If the paths are incompatible (e.g., absolute vs relative, or different roots).
 *
 * @example
 * ```php
 * echo relativePath( '/foo/bar/baz' , '/foo'     ) . PHP_EOL; // 'bar/baz'
 * echo relativePath( '/foo/baz'     , '/foo/bar' ) . PHP_EOL; // '../baz'
 * echo relativePath( '/foo/bar'     , '/foo/bar' ) . PHP_EOL; // '.'
 * echo relativePath( '/a/b'         , '/a/b/c/d' ) . PHP_EOL; // '../../'
 * echo relativePath( '/a/b/c'       , '/a'       ) . PHP_EOL; // 'b/c'
 * echo relativePath( '/a/x/y'       , '/a/b/c'   ) . PHP_EOL; // '../../x/y'
 *
 * echo relativePath( 'foo/bar/baz'  , 'foo'     ) . PHP_EOL; // 'bar/baz'
 * echo relativePath( 'foo/baz'      , 'foo/bar' ) . PHP_EOL; // '../baz'
 * echo relativePath( 'foo/bar'      , 'foo/bar' ) . PHP_EOL; // '.'
 * ```
 *
 * @package oihana\files\path
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
function relativePath( string $path , string $basePath ) :string
{
    $path     = canonicalizePath($path);
    $basePath = canonicalizePath($basePath);

    [ $root     , $relativePath     ] = splitPath( $path );
    [ $baseRoot , $relativeBasePath ] = splitPath( $basePath );

    // Case 1: Target path is relative, base path is absolute - Not supported
    if ($root === Char::EMPTY && $baseRoot !== Char::EMPTY)
    {
        throw new InvalidArgumentException
        (
            sprintf
            (
                'The target path "%s" is relative, but the base path "%s" is absolute. This combination is not supported.',
                $path,
                $basePath
            )
        );
    }

    // Case 2: Target path is absolute, base path is relative - Not supported
    if ( $root !== Char::EMPTY && $baseRoot === Char::EMPTY )
    {
        throw new InvalidArgumentException( sprintf
        (
            'The absolute path "%s" cannot be made relative to the relative path "%s". You should provide an absolute base path instead.',
            $path,
            $basePath
        ));
    }

    // Case 3: Paths have different roots (e.g., 'C:/' and 'D:/')
    if ( $baseRoot !== Char::EMPTY && $baseRoot !== $root )
    {
        throw new InvalidArgumentException( sprintf
        (
            'The path "%s" cannot be made relative to "%s", because they have different roots ("%s" and "%s").',
            $path,
            $basePath,
            $root,
            $baseRoot
        ));
    }

    // Handle edge cases
    if ( $relativePath === $relativeBasePath )
    {
        return '.' ;
    }

    if ( $relativeBasePath === Char::EMPTY )
    {
        return $relativePath;
    }

    // if ( $relativePath === Char::EMPTY )
    // {
    //     return '.';
    // }

    // Both paths are now of the same type, compute relative path
    return computeRelativePath( $relativePath , $relativeBasePath ) ;
}