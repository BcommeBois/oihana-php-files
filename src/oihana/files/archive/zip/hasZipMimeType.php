<?php

namespace oihana\files\archive\zip;

use function oihana\files\hasMimeType;

/**
 * Checks if a file has a zip-related MIME type.
 *
 * This function inspects the MIME type of the given file against a list
 * of valid zip-related MIME types to determine if the file is a zip archive.
 *
 * It is a thin wrapper around {@see \oihana\files\hasMimeType()} pre-configured
 * with the common zip MIME types.
 *
 * @param string   $filePath  Path to the file.
 * @param string[] $mimeTypes Optional list of valid zip MIME types.
 *                            Defaults to common zip types:
 *                            - 'application/zip'
 *                            - 'application/x-zip'
 *                            - 'application/x-zip-compressed'
 *                            - 'application/zip-compressed'
 *                            - 'multipart/x-zip'
 *
 * @return bool True if the file exists and its MIME type matches one of the given zip MIME types.
 *
 * @package oihana\files\archive\zip
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.2.0
 *
 * @example
 * Check if a .zip file is a zip archive:
 * ```php
 * $result = hasZipMimeType('/path/to/archive.zip');
 * var_dump($result); // bool(true) or bool(false)
 * ```
 *
 * Check a non-existent file (returns false):
 * ```php
 * $result = hasZipMimeType('/path/to/missing.zip');
 * var_dump($result); // bool(false)
 * ```
 */
function hasZipMimeType( string $filePath , array $mimeTypes  =
[
    'application/zip',
    'application/x-zip',
    'application/x-zip-compressed',
    'application/zip-compressed',
    'multipart/x-zip'
]): bool
{
    return hasMimeType( $filePath , $mimeTypes );
}
