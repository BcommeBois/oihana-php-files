<?php

namespace oihana\files ;

use Exception;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

use oihana\files\exceptions\DirectoryException;

/**
 * Deletes a directory recursively.
 *
 * @param string|array|null $path Directory or segments to remove.
 * @param bool $assertable Whether to validate the resulting path. Defaults to true.
 * @param bool $isReadable Check if the directory is readable (Default true).
 * @param bool $isWritable Check if the directory is writable (Default false).
 *
 * @return bool Returns true if the directory is removed.
 *
 * @throws DirectoryException If the directory path is null, empty, or if the directory cannot be deleted.
 *
 * @example
 *
 * Create a temporary directory structure and then delete it.
 * ```php
 * $baseDir = sys_get_temp_dir() . '/temp_dir_to_delete';
 * $subDir = $baseDir . '/nested_dir';
 * mkdir($subDir, 0777, true); // Create nested directories
 * file_put_contents($baseDir . '/file.txt', 'content');
 *
 * try
 * {
 *     if ( deleteDirectory( $baseDir ) )
 *     {
 *           // The directory and all its contents are now removed.
 *           // is_dir($baseDir) will return false.
 *     }
 * }
 * catch (DirectoryException $e)
 * {
 *     // Handle potential permission errors or other issues.
 *     echo "Error: " . $e->getMessage();
 * }
 * ```
 *
 * Using an array to specify the path to delete.
 * ```php
 * $parentDir = sys_get_temp_dir();
 * $dirName = 'another_temp_dir';
 * mkdir($parentDir . '/' . $dirName);
 * try
 * {
 *     // The path will be resolved to '/path/to/temp/another_temp_dir' and deleted.
 *     if (deleteDirectory([$parentDir, $dirName]))
 *     {
 *         // The directory is now removed.
 *     }
 * }
 * catch (DirectoryException $e)
 * {
 *     // Handle error.
 * }
 * ```
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
function deleteDirectory( string|array|null $path , bool $assertable = true , bool $isReadable = true , bool $isWritable = true ): bool
{
    $directory = getDirectory( $path , $assertable , $isReadable , $isWritable ) ;

    try
    {
        $iterator = new RecursiveIteratorIterator
        (
            new RecursiveDirectoryIterator( $directory , FilesystemIterator::SKIP_DOTS ) ,
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ( $iterator as $file )
        {
            if ( $file->isDir() )
            {
                if (!@rmdir($file->getPathname()))
                {
                    throw new DirectoryException(sprintf('Failed to remove directory "%s".', $file->getRealPath()));
                }
            }
            else
            {
                if ( !@unlink( $file->getPathname() ) )
                {
                    throw new DirectoryException(sprintf('Failed to remove file "%s".', $file->getRealPath() ) ) ;
                }
            }
        }

        if ( !@rmdir( $directory ) )
        {
            throw new DirectoryException( sprintf('Failed to remove directory "%s".', $directory ) );
        }
    }
    catch ( DirectoryException $exception )
    {
        throw $exception ;
    }
    catch ( Exception $exception )
    {
        throw new DirectoryException( sprintf('An error occurred while deleting directory "%s": %s' , $directory , $exception->getMessage() ) ) ;
    }

    return true ;
}