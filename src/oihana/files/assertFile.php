<?php

namespace oihana\files ;

use oihana\files\exceptions\FileException;

/**
 * Asserts that a file exists and is accessible.
 * @param string|null $file The path of the file to check.
 * @return void
 * @throws FileException If the file path is null, empty, or if the file does not exist or is not accessible.
 */
function assertFile( ?string $file ): void
{
    if ( is_null( $file ) )
    {
        throw new FileException('The file path must not be null.' );
    }

    if ( empty( trim( $file ) ) )
    {
        throw new FileException('The file path must not be empty.' ) ;
    }

    if ( !is_file( $file ) )
    {
        throw new FileException(sprintf('The file path "%s" is not a valid file.' , $file ) ) ;
    }

    // Check if the file is readable (optional, but useful)
    if ( !is_readable( $file ) )
    {
        throw new FileException( sprintf('The file "%s" is not readable.' , $file ) ) ;
    }
}