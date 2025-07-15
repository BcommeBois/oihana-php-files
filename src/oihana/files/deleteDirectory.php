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
 * @throws DirectoryException If the directory path is null, empty, or if the directory cannot be deleted.
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

        foreach ($iterator as $file)
        {
            if ($file->isDir())
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