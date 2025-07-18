<?php

namespace oihana\files\path ;

use oihana\enums\Char;

/**
 * Returns the directory part of the path.
 *
 * This function normalizes and extracts the parent directory from a file path.
 * It handles edge cases not covered by PHP's built-in dirname(), such as:
 * - Preserving trailing slashes in Windows root paths (e.g., "C:/")
 * - Correctly handling URI schemes (e.g., "file:///path/to/file")
 * - Supporting UNC (network) paths on Windows (e.g., "\\server\share\folder")
 * - Returning an empty string when no directory is applicable
 *
 * This method is similar to PHP's dirname(), but handles various cases
 * where dirname() returns a weird result:
 *
 * - dirname() does not accept backslashes on UNIX
 * - dirname("C:/symfony") returns "C:", not "C:/"
 * - dirname("C:/") returns ".", not "C:/"
 * - dirname("C:") returns ".", not "C:/"
 * - dirname("symfony") returns ".", not ""
 * - dirname() does not canonicalize the result
 *
 * This method fixes these shortcomings and behaves like dirname()
 * otherwise.
 *
 * The result is a canonical path.
 *
 * @param string $path The file path from which to extract the directory.
 *
 * @return string The directory part of the path, or an empty string if no directory can be extracted.
 *
 * @example
 * Unix-style paths
 * ```php
 * directoryPath('/var/www/html/file.txt'); // Returns '/var/www/html'
 * ```
 * Windows-style paths
 * ```php
 * directoryPath('C:\Windows\System32\file.txt'); // Returns 'C:\Windows\System32'
 * directoryPath('D:/Program Files/My App/file.txt'); // Returns 'D:/Program Files/My App'
 * ```
 *
 * Paths with URI schemes
 * ```php
 * directoryPath('file:///home/user/doc.txt'); // Returns '/home/user'
 * ```
 *
 * Edge cases
 * ```php
 * directoryPath('file.txt'); // Returns ''
 * directoryPath(''); // Returns ''
 * ```
 *
 * @package oihana\files\path
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
function directoryPath( string $path ) :string
{
    // 1. Path is empty

    if ( $path === Char::EMPTY )
    {
        return Char::EMPTY ;
    }

    // 2. Use back slashes

    $usesBackslashes = str_contains($path, '\\' );

    // 3. Schema

    $schemePos = strpos( $path , '://' ) ;
    if ( $schemePos !== false )
    {
        $scheme = substr( $path , 0 , $schemePos + 3 );
        $path   = substr( $path , $schemePos + 3 ) ;
        // Keep the schema except "file://"
        $keepScheme = !preg_match('/^file:\/\/$/i', $scheme ) ;
    }
    else
    {
        $scheme     = Char::EMPTY ;
        $keepScheme = false;
    }

    // 4. Check UNC

    $isUnc = str_starts_with($path, '\\\\') || str_starts_with( $path, Char::DOUBLE_SLASH ) ;

    $uncPrefix = Char::EMPTY ;
    if ( $isUnc )
    {
        $uncPrefix = Char::DOUBLE_SLASH ;
        $path = ltrim( $path , '/\\' ) ; // retire seulement ENTÊTE UNC
    }

    $path = canonicalizePath( $path ) ;

    if ( $isUnc )
    {
        $path     = $uncPrefix . ltrim($path, Char::SLASH );
        $segments = explode(Char::SLASH , ltrim($path, Char::SLASH ) ) ;
        if ( count($segments) >= 2 )
        {
            $dir = $uncPrefix . $segments[0] . '/' . $segments[1];
        }
        else
        {
            return Char::EMPTY ; // UNC invalid
        }

        if ( $usesBackslashes )
        {
            $dir = str_replace(Char::SLASH , Char::BACK_SLASH , $dir ) ;
        }
        return $dir;
    }

    // 4. Classical case

    $dirPos = strrpos( $path , Char::SLASH ) ;

    if ( $dirPos === false )
    {
        return Char::EMPTY;
    }

    if ( $dirPos === 0 )
    {
        $dir = Char::SLASH ; // "/"
    }
    elseif ( $dirPos === 2 && ctype_alpha( $path[0] ) && $path[1] === Char::COLON )
    {
        $dir = substr( $path , 0 , 3 ) ; // "C:/"
    }
    else
    {
        $dir = substr( $path , 0 , $dirPos ) ; // normal folder
    }

    $dir = ( $keepScheme ? $scheme : Char::EMPTY ) . $dir;

    if ( $usesBackslashes )
    {
        $dir = str_replace(Char::SLASH, Char::BACK_SLASH , $dir ) ;
    }

    return $dir;
}