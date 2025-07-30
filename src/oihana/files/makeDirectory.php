<?php

namespace oihana\files ;

use oihana\files\enums\MakeDirectoryOption;
use oihana\files\exceptions\DirectoryException;

/**
 * Creates a directory if it does not exist and returns the path of the directory.
 *
 * @param null|array|string  $pathOrOptions The path of the directory to create.
 * @param int                $permissions   The permissions to set for the directory (default: 0755).
 * @param bool               $recursive     If true, creates parent directories as needed (default: true).
 * @param string|null        $owner         User name or ID to set as directory owner (optional).
 * @param string|null        $group         Group name or ID to set as directory group (optional).
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
 * Assign an user/group permissions :
 * ```php
 * makeDirectory('/var/www/mydir', 0775, true, 'www-data', 'www-data');
 * ```
 *
 * Use an associative array to creates the new directory
 * ```php
 * makeDirectory
 * ([
 *     'path'        => '/var/www/mydir',
 *     'permissions' => 0775,
 *     'recursive'   => true,
 *     'owner'       => 'www-data',
 *     'group'       => 'www-data',
 * ]);
 * ```
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 *
 * @see MakeDirectoryOption
 */
function makeDirectory
(
    null|array|string $pathOrOptions,
    int               $permissions = 0755 ,
    bool              $recursive   = true ,
    ?string           $owner       = null ,
    ?string           $group       = null
)
: ?string
{
    if ( is_array( $pathOrOptions ) )
    {
        $directory   = $pathOrOptions[ MakeDirectoryOption::PATH        ] ?? null ;
        $permissions = $pathOrOptions[ MakeDirectoryOption::PERMISSIONS ] ?? $permissions ;
        $recursive   = $pathOrOptions[ MakeDirectoryOption::RECURSIVE   ] ?? $recursive ;
        $owner       = $pathOrOptions[ MakeDirectoryOption::OWNER       ] ?? $owner ;
        $group       = $pathOrOptions[ MakeDirectoryOption::GROUP       ] ?? $group ;
    }
    else
    {
        $directory = $pathOrOptions;
    }

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

    if ( $owner !== null && !chown( $directory , $owner ) )
    {
        throw new DirectoryException(sprintf('Failed to change owner to "%s" for directory "%s".', $owner, $directory));
    }

    if ( $group !== null && !chgrp( $directory , $group ) )
    {
        throw new DirectoryException(sprintf('Failed to change group to "%s" for directory "%s".' , $group , $directory ) ) ;
    }

    return $directory ;
}