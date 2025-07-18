<?php

namespace oihana\files ;

use oihana\files\exceptions\DirectoryException;

/**
 * Creates a directory if it does not exist and returns the path of the directory.
 *
 * @param ?string $directory The path of the directory to create.
 * @param int $permissions The permissions to set for the directory (default: 0755).
 * @param bool $recursive If true, creates parent directories as needed (default: true).
 *
 * @return ?string Returns the path of the directory.
 *
 * @throws DirectoryException If the directory cannot be created.
 *
 * @example
 *
 * Basic usage: create a directory if it does not exist.
 * ```php
 * use function oihana\files\makeDirectory;
 *
 * $dir = 'cache/files';
 * try
 * {
 *     makeDirectory($dir);
 *     echo "Directory created or already exists: $dir";
 * }
 * catch ( DirectoryException $e )
 * {
 *     // Handle error
 * }
 * ```
 *
 * Create a directory with custom permissions:
 * ```php
 * try
 * {
 *     makeDirectory('data/output', 0777);
 * }
 * catch ( DirectoryException $e )
 * {
 *     // Handle permission or creation error
 * }
 * ```
 *
 * Create a nested directory with recursive option:
 * ```php
 * try
 * {
 *     makeDirectory('var/log/myapp/debug', 0755, true); // parent folders created
 * }
 * catch ( DirectoryException $e )
 * {
 *     // Handle error
 * }
 * ```
 *
 * Handle failure when directory path is invalid or not writable:
 * ```php
 * try
 * {
 *    makeDirectory(''); // Throws exception: empty path
 * }
 * catch (DirectoryException $e)
 * {
 *     echo $e->getMessage(); // Directory path cannot be null or empty.
 * }
 * ```
 *
 * Check if the returned path is usable:
 * ```php
 * $path = makeDirectory('tmp/test');
 * file_put_contents( $path . '/sample.txt', 'content' ) ;
 * ```
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
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