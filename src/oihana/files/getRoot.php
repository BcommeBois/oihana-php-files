<?php

namespace oihana\files ;

use oihana\enums\Char;

/**
 * Extracts the root directory component of a given path.
 *
 * This function identifies the root portion of a file system path, including handling of protocol schemes.
 * ( e.g., "file://", "s3://" )
 *
 * UNIX root ("/"), and Windows root ( e.g., "C:/" ).
 *
 * It returns the canonical root as a string, or an empty string if the path
 * is relative or empty.
 *
 * **Behavior:**
 * - `"file:///usr/bin"` → `"file:///"`
 * - `"/usr/bin"` → `"/"`
 * - `"C:\\Windows\\System32"` → `"C:/"`
 * - `"relative/path"` → `""` (empty string)
 *
 * @param string $path The input path, optionally with a scheme.
 * @return string The root component of the path, or an empty string if no root can be determined.
 *
 * @example
 * ```php
 * use function oihana\files\getRoot;
 *
 * echo getRoot("file:///var/log");        // "file:///"
 * echo getRoot("/usr/local/bin");         // "/"
 * echo getRoot("C:\\Windows\\System32");  // "C:/"
 * echo getRoot("D:");                     // "D:/"
 * echo getRoot("some/relative/path");     // ""
 * ```
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
function getRoot( string $path ) :string
{
    if ( $path === Char::EMPTY )
    {
        return Char::EMPTY ;
    }

    $schemeSeparatorPosition = strpos( $path , '://' ) ;
    if ( $schemeSeparatorPosition !== false )
    {
        $scheme = substr( $path , 0 , $schemeSeparatorPosition + 3 );
        $path   = substr( $path , $schemeSeparatorPosition + 3 ) ;
    }
    else
    {
        $scheme = Char::EMPTY ;
    }

    $firstCharacter = $path[0] ?? Char::EMPTY ;

    if ( $firstCharacter === Char::SLASH || $firstCharacter === Char::BACK_SLASH )
    {
        return $scheme. Char::SLASH ; // UNIX root "/" or "\" (Windows style)
    }

    $length = strlen( $path ) ;

    // Windows root
    if ( $length > 1 && ctype_alpha( $firstCharacter ) && $path[1] === Char::COLON )
    {
        // Case: "C:" is considered absolute.
        if ( $length === 2 ) // Special case: "C:"
        {
            return $scheme.$path . Char::SLASH ;
        }

        if ( $path[2] === Char::SLASH || $path[2] === Char::BACK_SLASH ) // Normal case: "C:/ or "C:\"
        {
            return $scheme.$firstCharacter.$path[1] . Char::SLASH ;
        }
    }

    return Char::EMPTY ;
}