<?php

namespace oihana\files\archive\zip;

use oihana\enums\Char;
use oihana\files\enums\FileExtension;

/**
 * Checks if a file has a zip-related extension.
 *
 * @param string   $filePath      Path to the file.
 * @param string[] $zipExtensions Optional list of valid zip-related extensions.
 *                                Defaults to the single `.zip` extension.
 *
 * @return bool True if the file has a recognized zip extension.
 *
 * @package oihana\files\archive\zip
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.2.0
 *
 * @example
 * Check a simple zip file:
 * ```php
 * var_dump( hasZipExtension('/path/to/archive.zip') ); // bool(true)
 * ```
 *
 * Check is case-insensitive:
 * ```php
 * var_dump( hasZipExtension('/path/to/ARCHIVE.ZIP') ); // bool(true)
 * ```
 *
 * Check a file with an unsupported extension:
 * ```php
 * var_dump( hasZipExtension('/path/to/archive.tar') ); // bool(false)
 * ```
 */
function hasZipExtension( string $filePath , array $zipExtensions = [ FileExtension::ZIP ] ): bool
{
    $extension = Char::DOT . strtolower( pathinfo( $filePath , PATHINFO_EXTENSION ) );
    return in_array( $extension , $zipExtensions , true );
}
