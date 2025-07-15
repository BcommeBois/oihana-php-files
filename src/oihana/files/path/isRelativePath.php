<?php

namespace oihana\files\path ;

/**
 * Determines whether a given path is relative.
 *
 * A path is considered relative if it is not absolute. This function is the
 * direct inverse of `isAbsolutePath()`. It will return true for paths that
 * do not start with a slash, a backslash, or a Windows drive letter.
 *
 * @package oihana\files\path
 *
 * @param string $path The path to check.
 *
 * @return bool True if the path is relative, false if it is absolute.
 *
 * @see isAbsolutePath()
 *
 * @example
 * // Relative paths will return true
 * isRelativePath('documents/report.pdf');    // true
 * isRelativePath('../images/pic.jpg');      // true
 * isRelativePath('file.txt');               // true
 *
 * // Absolute paths will return false
 * isRelativePath('/var/www');               // false
 * isRelativePath('C:\\Users\\Test');          // false
 * isRelativePath('D:/folder/');              // false
 * isRelativePath('C:');                       // false
 * isRelativePath('file:///c/Users/');        // false
 *
 * // Edge cases
 * isRelativePath('');                       // true (an empty path is not absolute)
 */
function isRelativePath( string $path ) :bool
{
    return !isAbsolutePath( $path ) ;
}