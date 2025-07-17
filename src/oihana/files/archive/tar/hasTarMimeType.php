<?php

namespace oihana\files\archive\tar;

/**
 * Checks if a file has a tar-related extension.
 *
 * @param string $filePath Path to the file.
 * @param string[] $mimeTypes The valid 'tar' mime-types.
 *
 * @return bool True if the file has a tar extension.
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

