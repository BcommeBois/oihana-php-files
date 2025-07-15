<?php

namespace oihana\files\path ;

use oihana\enums\Char;

/**
 * Checks whether a given canonical path **lies inside** a base directory.
 *
 * The comparison is done purely on **canonicalized strings**
 * (see {@see canonicalizePath()}); the filesystem is **not consulted**.
 *
 * Algorithm
 * ---------
 * 1. Canonicalize both `$basePath` and `$ofPath`.
 * 2. Right–trim the base (to avoid double slash issues).
 * 3. Append a trailing slash to both and use {@see str_starts_with()} to ensure
 *    the *whole* first segment matches – preventing false positives like
 *    `/var/www-legacy` being considered inside `/var/www`.
 *
 * @param string $basePath The supposed ancestor directory.
 * @param string $childPath The child path to test.
 *
 * @return bool True if *childPath* is equal to or contained in *basePath*.
 *
 * @example
 * ```php
 * isBasePath( '/var/www' , '/var/www/site/index.php' ); // true
 * isBasePath( '/var/www' , '/var/www' );                // true (exact match)
 * isBasePath( '/var/www' , '/var/www-legacy' );         // false
 * isBasePath( 'C:/Users' , 'C:/Users/Bob/file.txt') ;   // true (Windows)
 * ```
 */
function isBasePath( string $basePath , string $childPath ) :bool
{
    $basePath = canonicalizePath( $basePath  ) ;
    $ofPath   = canonicalizePath( $childPath ) ;
    return str_starts_with($ofPath . Char::SLASH , rtrim( $basePath , Char::SLASH ) . Char::SLASH );
}