<?php

namespace oihana\files\path ;

use InvalidArgumentException;

/**
 * Turns a relative path into an absolute path in canonical form.
 *
 * This function joins a relative path with an absolute base path to create a new
 * absolute path. It also canonicalizes the result, which means:
 * - Dot segments ("." and "..") are resolved.
 * - All directory separators are converted to forward slashes ("/").
 * - Redundant slashes are removed.
 *
 * If the provided path is already absolute, it is simply canonicalized and
 * returned, ignoring the base path.
 *
 * The function correctly handles URI schemes (like "phar://" or "vfs://")
 * by preserving the scheme from the base path in the final result.
 *
 * @param string $path     The path to resolve. Can be relative or absolute.
 * @param string $basePath The absolute base path. Must not be empty.
 *
 * @return string The resulting absolute path in canonical form.
 *
 * @throws InvalidArgumentException If the base path is empty or not an absolute path.
 *
 * @package oihana\files\path
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 *
 * @example
 * ```php
 * // Basic usage with a Unix-style path
 * makeAbsolute('documents/../project/file.txt', '/home/user');
 * // => '/home/user/project/file.txt'
 *
 * // Usage with a Windows-style path
 * makeAbsolute('data\\.\\config.ini', 'C:\\Users\\Test');
 * // => 'C:/Users/Test/data/config.ini'
 *
 * // When the path is already absolute, the base path is ignored
 * makeAbsolute('/etc/app.conf', '/var/www');
 * // => '/etc/app.conf'
 *
 * // Correctly handles URI schemes
 * makeAbsolute('src/bootstrap.php', 'phar:///usr/local/bin/composer.phar');
 * // => 'phar:///usr/local/bin/composer.phar/src/bootstrap.php'
 * ```
 */
function makeAbsolute( string $path , string $basePath ) :string
{
    if ('' === $basePath)
    {
        throw new InvalidArgumentException( sprintf('The base path must be a non-empty string. Got: "%s".', $basePath ) );
    }

    if ( !isAbsolutePath( $basePath ) )
    {
        throw new InvalidArgumentException( sprintf('The base path "%s" is not an absolute path.', $basePath ) );
    }

    if ( isAbsolutePath( $path ) )
    {
        return canonicalizePath( $path ) ;
    }

    if ( false !== $schemeSeparatorPosition = strpos( $basePath , '://' ) )
    {
        $scheme   = substr( $basePath, 0, $schemeSeparatorPosition + 3);
        $basePath = substr( $basePath , $schemeSeparatorPosition + 3 ) ;
    }
    else
    {
        $scheme = '' ;
    }

    return $scheme . canonicalizePath( rtrim( $basePath , '/\\' ) .'/' . $path ) ;
}