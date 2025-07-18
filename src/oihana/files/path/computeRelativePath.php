<?php

namespace oihana\files\path ;

use oihana\enums\Char;

/**
 * Computes the relative path between two normalized relative paths.
 *
 * @param string $targetPath The target relative path.
 * @param string $basePath The base relative path.
 *
 * @return string The relative path from base to target.
 *
 * @example
 * ```php
 * echo computeRelativePath( 'foo/bar/baz' , 'foo'     ) . PHP_EOL; // 'bar/baz'
 * echo computeRelativePath( 'foo/baz'     , 'foo/bar' ) . PHP_EOL; // '../baz'
 * echo computeRelativePath( 'foo/bar'     , 'foo/bar' ) . PHP_EOL; // '.'
 * echo computeRelativePath( 'a/b'         , 'a/b/c/d' ) . PHP_EOL; // '../../'
 * echo computeRelativePath( 'a/b/c'       , 'a'       ) . PHP_EOL; // 'b/c'
 * echo computeRelativePath( 'a/x/y'       , 'a/b/c'   ) . PHP_EOL; // '../../x/y'
 * ```
 *
 * @package oihana\files\path
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
function computeRelativePath(string $targetPath, string $basePath): string
{
    $targetParts = explode(Char::SLASH, $targetPath);
    $baseParts   = explode(Char::SLASH, $basePath);

    // Remove empty parts (shouldn't happen with canonical paths, but safety first)
    $targetParts = array_filter( $targetParts , fn( $part ) => $part !== Char::EMPTY ) ;
    $baseParts   = array_filter( $baseParts   , fn( $part ) => $part !== Char::EMPTY ) ;

    // Find common prefix
    $commonLength = 0;
    $minLength = min(count($targetParts), count($baseParts));

    for ( $i = 0; $i < $minLength; $i++ )
    {
        if ( $targetParts[$i] === $baseParts[$i] )
        {
            $commonLength++;
        }
        else
        {
            break;
        }
    }

    // Calculate steps back from base to common ancestor
    $stepsBack = count($baseParts) - $commonLength;

    // Calculate steps forward from common ancestor to target
    $stepsForward = array_slice($targetParts, $commonLength);

    $result = str_repeat('../', $stepsBack) . implode(Char::SLASH, $stepsForward);

    if ( $result === Char::EMPTY )
    {
        return Char::DOT ;
    }

    return rtrim($result, Char::SLASH);
}