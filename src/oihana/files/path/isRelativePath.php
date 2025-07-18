<?php

namespace oihana\files\path ;

/**
 * Determines whether a given path is relative.
 *
 * A path is considered relative if it is not absolute. This function is the
 * direct inverse of `isAbsolutePath()`. It will return true for paths that
 * do not start with a slash, a backslash, or a Windows drive letter.
 *
 * @param string $path The path to check.
 *
 * @return bool True if the path is relative, false if it is absolute.
 *
 * @package oihana\files\path
 *
 * @see isAbsolutePath()
 *
 * @example
 * ```php
 * // Relative paths (returns true)
 * var_dump( isRelativePath( 'documents/report.pdf' ) ) ; // true
 * var_dump( isRelativePath( '../images/pic.jpg'    ) ) ; // true
 * var_dump( isRelativePath( 'file.txt'             ) ) ; // true
 * var_dump( isRelativePath( ''                     ) ) ; // true (empty path)
 *
 * // Absolute paths (returns false)
 * var_dump( isRelativePath( '/var/www'             ) ) ; // false
 * var_dump( isRelativePath( 'C:\\Users\\Test'      ) ) ; // false
 * var_dump( isRelativePath( 'D:/folder/'           ) ) ; // false
 * var_dump( isRelativePath( 'C:'                   ) ) ; // false
 * var_dump( isRelativePath( 'file:///c/Users/'     ) ) ; // false
 * ```
 *
 * @package oihana\files\path
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
function isRelativePath( string $path ) :bool
{
    return !isAbsolutePath( $path ) ;
}