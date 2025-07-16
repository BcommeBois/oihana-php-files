<?php

namespace oihana\files\archive;

use oihana\enums\Char;
use oihana\files\enums\FileExtension;

/**
 * Checks if a file has a tar-related extension.
 *
 * @param string $filePath Path to the file.
 * @param string[] $tarExtensions The valid tar extensions.
 *
 * @return bool True if the file has a tar extension.
 */
function hasTarExtension( string $filePath , array $tarExtensions  =
[
    FileExtension::TAR ,
    FileExtension::TGZ ,
    FileExtension::GZ ,
    FileExtension::TAR_GZ ,
    FileExtension::TAR_BZ2 ,
    FileExtension::BZ2
]): bool
{
    $extension = Char::DOT . strtolower( pathinfo( $filePath , PATHINFO_EXTENSION ) );

    $filename        = pathinfo( $filePath , PATHINFO_FILENAME );
    $secondExtension = Char::DOT . strtolower( pathinfo( $filename , PATHINFO_EXTENSION ) );
    $fullExtension   = $secondExtension . $extension;

    return in_array( $extension , $tarExtensions ) || in_array( $fullExtension , $tarExtensions );
}

