<?php

namespace oihana\files\archive\tar;

use function oihana\files\hasMimeType;

/**
 * Checks if a file has a tar-related extension.
 *
 * This function inspects the MIME type of the given file against a list
 * of valid tar-related MIME types to determine if the file is a tar archive.
 *
 * It is a thin wrapper around {@see \oihana\files\hasMimeType()} pre-configured
 * with the common tar MIME types.
 *
 * @param string   $filePath  Path to the file.
 * @param string[] $mimeTypes Optional list of valid tar MIME types.
 *                            Defaults to common tar and compressed tar types:
 *                            - 'application/x-tar'
 *                            - 'application/tar'
 *                            - 'application/gzip'
 *                            - 'application/x-gzip'
 *                            - 'application/x-bzip2'
 *                            - 'application/bzip2'
 *                            - 'application/x-compressed-tar'
 *
 * @return bool True if the file exists and its MIME type matches one of the given tar MIME types.
 *
 * @package oihana\files\archive\tar
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 *
 * @example
 * Check if a .tar.gz file is a tar archive:
 * ```php
 * $result = hasTarMimeType('/path/to/archive.tar.gz');
 * var_dump($result); // bool(true) or bool(false)
 * ```
 *
 * Check a file with a custom list of MIME types:
 * ```php
 * $customTypes = ['application/x-tar', 'application/x-custom-tar'];
 * $result = hasTarMimeType('/path/to/custom.tar', $customTypes);
 * ```
 *
 * Check a non-existent file (returns false):
 * ```php
 * $result = hasTarMimeType('/path/to/missing.tar');
 * var_dump($result); // bool(false)
 * ```
 */
function hasTarMimeType( string $filePath , array $mimeTypes  =
[
    'application/x-tar',
    'application/tar',
    'application/gzip',
    'application/x-gzip',
    'application/x-bzip2',
    'application/bzip2',
    'application/x-compressed-tar'
]): bool
{
    return hasMimeType( $filePath , $mimeTypes );
}

