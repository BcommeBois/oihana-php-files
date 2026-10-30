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
 * **Symbolic links are removed, never followed.** A link met in the tree is unlinked, whether it
 * points to a directory or to a file, and whether its target still exists or not. The target is
 * left untouched: nothing is ever deleted through a link. The same holds for `$path` itself — if
 * `$path` is a link to a directory, the link alone is removed and the directory it points to is
 * kept, so the call does not recurse at all.
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
 * A link inside the tree goes away with the tree, its target stays.
 * ```php
 * mkdir( '/tmp/tree' ) ;
 * mkdir( '/tmp/outside' ) ;
 * file_put_contents( '/tmp/outside/keepme.txt' , 'content' ) ;
 * symlink( '/tmp/outside' , '/tmp/tree/linked' ) ;
 *
 * deleteDirectory( '/tmp/tree' ) ;
 *
 * // is_dir('/tmp/tree') is false, '/tmp/outside/keepme.txt' is still there.
 * ```
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
function deleteDirectory( string|array|null $path , bool $assertable = true , bool $isReadable = true , bool $isWritable = true ): bool
{
    $directory = getDirectory( $path , $assertable , $isReadable , $isWritable ) ;

    // $path may itself be a link to a directory. Remove the link, keep its target, and do not
    // walk into it: a tree reached through a link is not this call's to delete.

    if ( isSymlink( $directory ) )
    {
        if ( !@unlink( $directory ) )
        {
            throw new DirectoryException( sprintf('Failed to remove symbolic link "%s".' , $directory ) ) ;
        }

        return true ;
    }

    try
    {
        $iterator = new RecursiveIteratorIterator
        (
            new RecursiveDirectoryIterator( $directory , FilesystemIterator::SKIP_DOTS ) ,
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ( $iterator as $file )
        {
            // isLink() comes before isDir(), and the order is the whole point: isDir() answers true
            // for a link to a directory, and rmdir() refuses a link. Reversed, the walk stops in the
            // middle of the tree. The link is also named by getPathname(), not getRealPath(), so a
            // failure reports the path that blocks instead of the target behind it.

            if ( $file->isLink() )
            {
                if ( !@unlink( $file->getPathname() ) )
                {
                    throw new DirectoryException( sprintf('Failed to remove symbolic link "%s".' , $file->getPathname() ) ) ;
                }
            }
            else if ( $file->isDir() )
            {
                if ( !@rmdir( $file->getPathname() ) )
                {
                    throw new DirectoryException( sprintf('Failed to remove directory "%s".' , $file->getPathname() ) ) ;
                }
            }
            else
            {
                if ( !@unlink( $file->getPathname() ) )
                {
                    throw new DirectoryException( sprintf('Failed to remove file "%s".', $file->getPathname() ) ) ;
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
