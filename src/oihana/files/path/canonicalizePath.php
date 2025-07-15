<?php

namespace oihana\files\path ;

use oihana\enums\Char;
use oihana\files\helpers\CanonicalizeBuffer as Buffer;

use function oihana\files\getHomeDirectory;

/**
 * Converts any given path to a **canonical, absolute‑style** representation.
 *
 * The algorithm:
 * 1. **Early return / cache** – looks up&nbsp;the path in a static LRU‑style buffer
 *    (see {@see CanonicalizeBuffer}).
 * 2. **Home expansion** – replaces a leading tilde `~` with the current user’s
 *    home directory (platform‑aware via {@see getHomeDirectory()}).
 * 3. **Separator normalisation** – back‑slashes ⇒ forward‑slashes via
 *    {@see normalizePath()}.
 * 4. **Root / remainder split** – handled by {@see splitPath()}.
 * 5. **Dot & dot‑dot cleanup** – collapses `.` and `..` segments with
 *    {@see extractCanonicalParts()}.
 * 6. **Buffer write‑back** – stores the result; periodically cleans the buffer
 *    after {@link CanonicalizeBuffer::CLEANUP_THRESHOLD} insertions.
 *
 * **No filesystem access** is performed; non‑existent paths are allowed.
 *
 * @param string $path The path to canonicalise. May be relative or absolute,
 *                     Windows or Unix, and may start with `~`.
 *
 * @return string Canonicalised path with forward slashes and no redundant
 *                `.` / `..` segments.
 *
 * @example
 * ```php
 * canonicalizePath('~/projects/../site//index.php');
 * // -> "/home/alice/site/index.php"   (Linux)
 *
 * canonicalizePath('C:\\Temp\\..\\Logs\\.');
 * // -> "C:/Logs"
 * ```
 *
 * @see normalizePath()
 * @see splitPath()
 * @see extractCanonicalParts()
 */
function canonicalizePath( string $path ) :string
{
    if ( $path === Char::EMPTY )
    {
        return Char::EMPTY ;
    }

    // Cached result
    if (isset( Buffer::$buffer[ $path ] ) )
    {
        return Buffer::$buffer[ $path ] ;
    }

    // Expand the "~" path
    if ( $path[0] === Char::TILDE )
    {
        $path = getHomeDirectory().substr( $path , 1 ) ;
    }

    // Unify separators
    $path = normalizePath($path);

    [ $root , $rest ] = splitPath( $path ) ;

    // Root handling + dot‑segment
    $parts         = extractCanonicalParts($root, $rest);
    $canonicalPath = $root . implode(Char::SLASH, $parts);

    // Cache result with soft‑LRU clean‑up
    Buffer::$buffer[$path] = $canonicalPath ;
    if ( ++Buffer::$bufferSize > Buffer::CLEANUP_THRESHOLD )
    {
        Buffer::$buffer      = array_slice( Buffer::$buffer, -Buffer::CLEANUP_SIZE, null, true ) ;
        Buffer::$bufferSize = Buffer::CLEANUP_SIZE;
    }

    return $canonicalPath;
}