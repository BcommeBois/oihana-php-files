<?php

namespace oihana\files ;

use oihana\enums\Char;
use oihana\files\exceptions\DirectoryException;

/**
 * Asserts that a directory exists and is accessible.
 *
 * @param string|null $path The path of the directory to check.
 * @param bool $isReadable Check if the directory is readable.
 * @param bool $isWritable Check if the directory is writable.
 * @return void
 * @throws DirectoryException If the directory path is null, empty, or if the directory does not exist or is not accessible.
 */
function assertDirectory( ?string $path , bool $isReadable = true , bool $isWritable = false ): void
{
    if ( $path === null )
    {
        throw new DirectoryException('The directory path must not be null.' ) ;
    }

    $path = trim( $path ) ;
    if ( $path === Char::EMPTY )
    {
        throw new DirectoryException('The directory path must not be empty.' ) ;
    }

    if ( !is_dir( $path ) )
    {
        throw new DirectoryException( sprintf('The path "%s" is not a valid directory.' , $path ) ) ;
    }

    if ( $isReadable )
    {
        if (!is_readable($path))
        {
            throw new DirectoryException(sprintf('The directory "%s" is not readable.', $path ) );
        }
    }

    if ( $isWritable && !is_writable( $path ) )
    {
        throw new DirectoryException( sprintf('The directory "%s" is not writable.' , $path ) ) ;
    }
}