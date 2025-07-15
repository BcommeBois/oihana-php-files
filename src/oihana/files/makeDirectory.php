<?php

namespace oihana\files ;

use oihana\files\exceptions\DirectoryException;

/**
 * Creates a directory if it does not exist and returns the path of the directory.
 * @param ?string $directory The path of the directory to create.
 * @param int $permissions The permissions to set for the directory (default: 0755).
 * @param bool $recursive If true, creates parent directories as needed (default: true).
 * @return ?string Returns the path of the directory.
 * @throws DirectoryException If the directory cannot be created.
 */
function makeDirectory( ?string $directory , int $permissions = 0755 , bool $recursive = true ): ?string
{
    if ( empty( $directory ) )
    {
        throw new DirectoryException('Directory path cannot be null or empty.' ) ;
    }

    if ( is_dir( $directory ) )
    {
        if ( !is_writable( $directory ) )
        {
            throw new DirectoryException( sprintf('Directory "%s" is not writable.', $directory ) );
        }
        return $directory ;
    }

    if( !mkdir( $directory , $permissions , $recursive ) && !is_dir( $directory ) )
    {
        throw new DirectoryException( sprintf( 'Failed to create directory "%s".' , $directory ) ) ;
    }

    if ( !is_dir( $directory ) )
    {
        throw new DirectoryException(sprintf('Directory "%s" was not created.' , $directory ) ) ;
    }

    if ( !is_writable( $directory ) )
    {
        throw new DirectoryException( sprintf('Directory "%s" is not writable.' , $directory ) ) ;
    }

    return $directory ;
}