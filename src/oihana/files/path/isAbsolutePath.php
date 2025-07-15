<?php

namespace oihana\files\path ;

use oihana\enums\Char;

/**
 * Determines whether a given path is absolute.
 *
 * This function checks for both UNIX-style absolute paths (starting with "/")
 * and Windows-style absolute paths (e.g., "C:\", "C:/", or just "C:").
 * It also handles paths with URI schemes like "file://" by stripping the
 * scheme before analysis.
 *
 * @package oihana\files\path
 *
 * @param string $path The path to check.
 *
 * @return bool True if the path is absolute, false otherwise.
 *
 * @example
 * // Unix-style paths
 * isAbsolutePath('/var/www'); // true
 *
 * // Windows-style paths
 * isAbsolutePath('C:\\Users\\Test'); // true
 * isAbsolutePath('D:/folder/file.txt'); // true
 * isAbsolutePath('C:'); // true
 * isAbsolutePath('\\network-share\folder'); // true
 *
 * // Paths with schemes
 * isAbsolutePath('file:///c/Users/'); // true
 *
 * // Relative paths
 * isAbsolutePath('documents/report.pdf'); // false
 * isAbsolutePath('../images/pic.jpg'); // false
 * isAbsolutePath('file.txt'); // false
 *
 * // Edge cases
 * isAbsolutePath(''); // false
 * isAbsolutePath('/'); // true
 * isAbsolutePath('C:/'); // true
 */
function isAbsolutePath( string $path ) :bool
{
    // 1. An empty path is not absolute.
    if ( $path == Char::EMPTY )
    {
        return false ;
    }

    // 2. If a scheme is present (e.g., "file://"), strip it to analyze the path part.

    $schemeSeparatorPosition = strpos( $path , '://' ) ;

    if ( $schemeSeparatorPosition !== false )
    {
        $path = substr( $path , $schemeSeparatorPosition + 3 ) ;
    }

    // 3. Covers UNIX root "/" and Windows roots like "\" or "\\network-share".

    $firstCharacter = $path[0] ?? Char::EMPTY ;

    if ( $firstCharacter === Char::SLASH || $firstCharacter === Char::BACK_SLASH )
    {
        return true ;
    }

    // 4. A path is also absolute if it's a Windows path with a drive letter.

    $length = strlen( $path ) ;

    if ( $length > 1 && ctype_alpha( $firstCharacter ) && $path[1] === Char::COLON )
    {
        // Case: "C:" is considered absolute.
        if ( $length === 2 )
        {
            return true ;
        }

        // Case: "C:/" or "C:\"
        if ( $path[2] === Char::SLASH || $path[2] === Char::BACK_SLASH ) // Normal case: "C:/ or "C:\"
        {
            return true ;
        }
    }

    return false;
}