<?php

namespace oihana\files\archive\tar;

/**
 * Checks if a file has a tar-related extension.
 *
 * This function inspects the MIME type of the given file against a list
 * of valid tar-related MIME types to determine if the file is a tar archive.
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
    if ( !is_file( $filePath ) )
    {
        return false;
    }

    $info = finfo_open( FILEINFO_MIME_TYPE );
    if ( $info === false )
    {
        return false;
    }

    $mimeType = finfo_file( $info , $filePath );
    finfo_close( $info );

    if ( $mimeType === false )
    {
        return false;
    }

    return array_any( $mimeTypes , fn( $validType ) => str_contains( $mimeType , $validType ) );
}

