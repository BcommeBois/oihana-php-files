<?php

namespace oihana\files ;

use oihana\enums\Char;
use oihana\files\exceptions\FileException;

/**
 * Asserts that a file exists and is accessible.
 *
 * @param string|null $file The path of the file to check.
 * @param array|null $expectedMimeTypes
 * @param bool $isReadable Whether to assert that the directory is readable. Default: true.
 * @param bool $isWritable Whether to assert that the directory is writable. Default: false.
 *
 * @return void
 *
 * @throws FileException If the file path is null, empty, or if the file does not exist or is not accessible.
 *
 * @example
 *
 * Basic usage: check if a file exists and is readable.
 * ```php
 * $file = 'test.txt';
 * file_put_contents($file, 'data');
 * try
 * {
 *    assertFile($file);
 *     // Continue ...
 * }
 * catch (FileException $e)
 * {
 *     // Handle error...
 * }
 * unlink($file);
 * ```
 *
 * Check for specific MIME types.
 * ```php
 * $file = 'document.txt';
 * file_put_contents($file, 'some text');
 * try
 * {
 *      // Will pass because a .txt file is typically 'text/plain'.
 *      assertFile( $file , ['text/plain', 'application/pdf'] );
 * }
 * catch ( FileException $e )
 * {
 *     // Throws an exception if MIME type is not in the allowed list.
 * }
 * unlink($file);
 * ```
 *
 * Check if a file is writable.
 * ```php
 * $file = 'config.ini';
 * file_put_contents($file, '[settings]');
 * try
 * {
 *     // Asserts the file exists, is readable, and is writable.
 *     assertFile($file, null, true, true);
 * }
 * catch (FileException $e)
 * {
 *     // Throws an exception if file is not writable.
 * }
 * unlink($file);
 * ```
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
function assertFile( ?string $file , ?array $expectedMimeTypes = null , bool $isReadable = true , bool $isWritable = false ): void
{
    if ( is_null( $file ) )
    {
        throw new FileException('The file path must not be null.' );
    }

    $file = trim( $file ) ;

    if ( $file === Char::EMPTY )
    {
        throw new FileException('The file path must not be empty.' ) ;
    }

    if ( !is_file( $file ) )
    {
        throw new FileException( sprintf('The file path "%s" is not a valid file.' , $file ) ) ;
    }

    if ( $isReadable && !is_readable( $file ) )
    {
        throw new FileException( sprintf('The file "%s" is not readable.' , $file ) ) ;
    }

    if ( $isWritable && !is_writable( $file ) )
    {
        throw new FileException( sprintf('The file "%s" is not readable.' , $file ) ) ;
    }

    // If MIME types are provided, validate them
    if( !empty( $expectedMimeTypes ) )
    {
        validateMimeType( $file, $expectedMimeTypes ) ;
    }
}