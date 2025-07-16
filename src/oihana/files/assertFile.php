<?php

namespace oihana\files ;

use oihana\enums\Char;
use oihana\files\exceptions\FileException;

/**
 * Asserts that a file exists and is accessible.
 *
 * @param string|null $file The path of the file to check.
 * @param array|null $expectedMimeTypes
 * @param bool $isReadable Whether to assert that the directory is readable. Default: true.
 * @param bool $isWritable Whether to assert that the directory is writable. Default: false.
 *
 * @return void
 *
 * @throws FileException If the file path is null, empty, or if the file does not exist or is not accessible.
 */
function assertFile( ?string $file , ?array $expectedMimeTypes = null , bool $isReadable = true , bool $isWritable = false ): void
{
    if ( is_null( $file ) )
    {
        throw new FileException('The file path must not be null.' );
    }

    $file = trim( $file ) ;

    if ( $file === Char::EMPTY )
    {
        throw new FileException('The file path must not be empty.' ) ;
    }

    if ( !is_file( $file ) )
    {
        throw new FileException( sprintf('The file path "%s" is not a valid file.' , $file ) ) ;
    }

    if ( $isReadable && !is_readable( $file ) )
    {
        throw new FileException( sprintf('The file "%s" is not readable.' , $file ) ) ;
    }

    if ( $isWritable && !is_writable( $file ) )
    {
        throw new FileException( sprintf('The file "%s" is not readable.' , $file ) ) ;
    }

    // If MIME types are provided, validate them
    if( !empty( $expectedMimeTypes ) )
    {
        validateMimeType( $file, $expectedMimeTypes ) ;
    }
}