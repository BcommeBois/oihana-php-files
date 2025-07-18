<?php

namespace oihana\files ;

use oihana\enums\Char;
use oihana\files\exceptions\DirectoryException;

/**
 * Asserts that a directory exists and is accessible, optionally checking readability and writability.
 *
 * @param string|null $path The path of the directory to check.
 * @param bool $isReadable Whether to assert that the directory is readable. Default: true.
 * @param bool $isWritable Whether to assert that the directory is writable. Default: false.
 * @param int|null $expectedPermissions Optional permission mask (e.g., 0755).
 *
 * @return void
 *
 * @throws DirectoryException If the path is null, empty, not a directory, or fails accessibility checks.
 *
 * @example
 * ```php
 * try
 * {
 *     $directoryPath = '/chemin/vers/le/repertoire' ;
 *
 *     assertDirectory( $directoryPath , true , true , 0755 ) ;
 *
 *     echo "The directory is accessible with the good permissions.\n";
 * }
 * catch ( DirectoryException $e )
 * {
 *     echo "Error: " . $e->getMessage() . PHP_EOL ;
 * }
 * ```
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
function assertDirectory
(
    ?string $path ,
    bool $isReadable = true ,
    bool $isWritable = false ,
    ?int $expectedPermissions = null
) : void
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

    if ($expectedPermissions !== null )
    {
        $actualPermissions = fileperms( $path ) & 0o777;
        if ($actualPermissions !== $expectedPermissions)
        {
            throw new DirectoryException( sprintf
            (
                'The directory "%s" has permissions "%o", expected "%o".',
                $path,
                $actualPermissions,
                $expectedPermissions
            ));
        }
    }
}