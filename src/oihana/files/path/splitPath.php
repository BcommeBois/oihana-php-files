<?php

namespace oihana\files\path ;

use oihana\enums\Char;

/**
 * Splits a **canonical** filesystem path into two parts:
 *   1. **Root** : the protocol / drive / leading slash portion
 *   2. **Remainder** : the sub‑path that follows
 *
 * Supported patterns
 * ------------------
 * | Input example                        | Returned **root**     | Returned **remainder** |
 * |--------------------------------------|-----------------------|------------------------|
 * | `/var/www/html`                      | `/`                   | `var/www/html`         |
 * | `C:/Windows/System32`                | `C:/`                 | `Windows/System32`     |
 * | `C:`                                 | `C:/`                 | *(empty)*              |
 * | `file:///home/user/docs`             | `file:///`            | `home/user/docs`       |
 * | `\\\\server\\share\\folder` (UNC)    | `//`                  | `server/share/folder`  |
 *
 * > **Note :** The input path is assumed to be _canonical_ (slashes normalisés
 * > à `/`, pas de segments `.` ou `..`).
 * > The function does **not** perform any filesystem checks.
 *
 * @param string $path Canonical path (absolute or relative, UNIX or Windows, with
 *                     optional URL‑style scheme).
 *
 * @return string[] An array with **[0] => root, [1] => remainder**.
 *                  Both strings can be empty.
 *
 * @example
 * ```php
 * [$root, $rest] = splitPath('/etc/nginx/nginx.conf');
 * // $root = '/'          | $rest = 'etc/nginx/nginx.conf'
 *
 * [$root, $rest] = splitPath('C:/Program Files');
 * // $root = 'C:/'        | $rest = 'Program Files'
 *
 * [$root, $rest] = splitPath('C:');
 * // $root = 'C:/'        | $rest = ''
 *
 * [$root, $rest] = splitPath('file:///var/log');
 * // $root = 'file:///'   | $rest = 'var/log'
 * ```
 */
function splitPath( string $path ) :array
{
    if ( $path === Char::EMPTY )
    {
        return [ Char::EMPTY , Char::EMPTY ] ;
    }

    // 1. Extract URL‑style scheme (e.g. "file://", "phar://")

    if ( false !== $schemeSeparatorPosition = strpos( $path, '://' ) )
    {
        $root = substr( $path , 0 , $schemeSeparatorPosition + 3 ) ;
        $path = substr( $path , $schemeSeparatorPosition + 3);
    }
    else
    {
        $root = Char::EMPTY ;
    }

    $length = strlen( $path ) ;

    // 2. UNIX absolute path
    if ( str_starts_with( $path, Char::SLASH ) )
    {
        $root .= Char::SLASH ;
        $path = $length > 1 ? substr($path, 1) : Char::EMPTY ;
    }
    // 3. Windows drive letter
    elseif ( $length > 1 && ctype_alpha( $path[0]) && Char::COLON === $path[1] )
    {
        if ( $length === 2 ) // Windows special case: "C:"
        {
            $root .= $path . Char::SLASH ;
            $path   = Char::EMPTY  ;
        }
        elseif ( $path[2] === Char::SLASH ) // Windows normal case: "C:/"..
        {
            $root .= substr( $path , 0 , 3 ) ;
            $path = $length > 3 ? substr( $path , 3 ) : Char::EMPTY ;
        }
    }

    return [ $root , $path ] ;
}