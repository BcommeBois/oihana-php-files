<?php

namespace oihana\files\path ;

use oihana\enums\Char;

/**
 * Determines whether the given path refers to a local file system location.
 *
 * A path is considered "local" if:
 * - It is not empty.
 * - It does not contain a URL-like scheme (e.g. `http://`, `ftp://`, `s3://`, etc.).
 *
 * This function uses a lightweight string check to detect the presence of `://`,
 * which is common in remote paths or stream wrappers.
 *
 * @param string $path The path to check. Can be relative, absolute, or URL-style.
 *
 * @return bool True if the path is local; false if it looks like a remote or virtual path.
 *
 * @example
 * ```php
 * isLocalPath('/var/log/app.log');     // true
 * isLocalPath('C:\\Users\\Admin');     // true
 * isLocalPath('https://example.com');  // false
 * isLocalPath('s3://my-bucket/file');  // false
 * ```
 */
function isLocalPath( string $path ) :bool
{
    return $path !== Char::EMPTY && !str_contains( $path, '://' ) ;
}