<?php

namespace oihana\files ;

use oihana\files\exceptions\FileException;

/**
 * Deletes a file from the filesystem.
 *
 * This function optionally asserts that the file exists and meets
 * the specified readability and writability requirements before attempting deletion.
 * If the deletion fails, a `FileException` is thrown.
 *
 * @param string $filePath The path to the file to delete.
 * @param bool $assertable Whether to perform assertions on the file before deletion (default: true).
 * @param bool $isReadable Whether to assert that the file is readable (default: true).
 * @param bool $isWritable Whether to assert that the file is writable (default: true).
 *
 * @return bool Returns true on successful deletion.
 *
 * @throws FileException If the file does not meet the assertions or cannot be deleted.
 *
 * @example
 * ```php
 * $file = 'example.txt';
 * file_put_contents($file, 'Sample content');
 *
 * try
 * {
 *     deleteFile($file);
 *     echo "File deleted successfully.";
 * }
 * catch (FileException $e)
 * {
 *     echo "Error deleting file: " . $e->getMessage();
 * }
 * ```
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
function deleteFile( string $filePath, bool $assertable = true, bool $isReadable = true, bool $isWritable = true ): bool
{
    if ( $assertable )
    {
        assertFile( $filePath , null , $isReadable , $isWritable );
    }

    if ( ! @unlink( $filePath ) )
    {
        throw new FileException( sprintf( 'Failed to remove file "%s".' , $filePath ) ) ;
    }

    return true;
}