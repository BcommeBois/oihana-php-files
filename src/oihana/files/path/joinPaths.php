<?php

namespace oihana\files\path ;

use oihana\enums\Char;

/**
 * Concatenates multiple path fragments into a single canonical path.
 *
 * Behaviour rules
 * ---------------
 * 1. **Empty segments** (`''`) are ignored.
 * 2. The *first* non‑empty segment is kept “as‑is” so a leading slash, drive
 *    letter (`C:/`), or scheme (`phar://`) is preserved.
 * 3. Every subsequent segment is joined with **exactly one** forward‑slash
 *    (`/`) separator – unless the previous fragment already ends with `/` or `\`.
 * 4. After assembly, the result is passed through {@see canonicalizePath()} to
 *    normalise slashes and collapse `.` / `..`.
 * 5. If all fragments are empty, an empty string is returned.
 *
 * ⚠ **Type‑hint fix** – the function returns a *string*, not a *bool*.
 *   The signature has been updated accordingly.
 *
 * @param string ...$paths Arbitrary number of path fragments. May contain
 *                         Unix, Windows or URL‑style segments.
 *
 * @return string Canonical, joined path (or empty string).
 *
 * @example
 * ```php
 * joinPaths('/var', 'log', 'app.log');
 * // → '/var/log/app.log'
 *
 * joinPaths('C:\\', 'Temp', '..', 'Logs');
 * // → 'C:/Logs'
 *
 * joinPaths('phar://archive.phar', '/sub', '/file.php');
 * // → 'phar://archive.phar/sub/file.php'
 *
 * joinPaths('', 'relative', 'path');   // leading blanks ignored
 * // → 'relative/path'
 * ```
 */
function joinPaths( string ...$paths ) :string
{
    $finalPath = null;
    $wasScheme = false;

    foreach ($paths as $path)
    {
        if ( $path === Char::EMPTY )
        {
            continue ;
        }

        if ( $finalPath === null ) // For first part we keep slashes, like '/top', 'C:\' or 'phar://'
        {
            $finalPath = $path;
            $wasScheme = str_contains($path, '://');
            continue;
        }

        // Only add slash if previous part didn't end with '/' or '\'
        if ( !in_array( substr( $finalPath , -1 ) , [ Char::SLASH , Char::BACK_SLASH ] , true ) )
        {
            $finalPath .= Char::SLASH ;
        }

        // If first part included a scheme like 'phar://' we allow \current part to start with '/', otherwise trim
        $finalPath .= $wasScheme ? $path : ltrim( $path , Char::SLASH ) ;
        $wasScheme = false;
    }

    if ( $finalPath === null )
    {
        return Char::EMPTY ;
    }

    return canonicalizePath( $finalPath ) ;
}