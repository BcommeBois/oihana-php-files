<?php

namespace oihana\files ;

use oihana\files\exceptions\FileException;

/**
 * Validate the MIME type of a file against a list of allowed types.
 *
 * @param string $file Path to the file to validate.
 * @param array $allowedMimeTypes List of allowed MIME types. Can include strings or arrays of strings.
 *
 * @return void
 *
 * @throws FileException If the MIME type is not allowed or cannot be determined.
 *
 * @example
 *
 * Basic usage: validate that an uploaded file is a PDF or plain text file.
 * ```php
 * use function oihana\files\validateMimeType;
 * use oihana\files\exceptions\FileException;
 *
 * $file = __DIR__ . '/example.txt';
 * file_put_contents($file, 'Some text content');
 *
 * try
 * {
 *     validateMimeType($file, ['text/plain', 'application/pdf']);
 *     echo "File is valid.";
 * }
 * catch ( FileException $e )
 * {
 *     echo "Error: " . $e->getMessage();
 * }
 *
 * unlink($file);
 * ```
 *
 * Accepting multiple MIME types (grouped by type):
 * ```php
 * $allowedTypes =
 * [
 *     ['image/png', 'image/jpeg'],
 *     ['application/pdf'],
 * ];
 * validateMimeType('photo.jpg', $allowedTypes);
 * ```
 *
 * Error example:
 * ```php
 * try
 * {
 *     validateMimeType('fake.exe', ['image/png', 'image/jpeg']);
 * }
 * catch (FileException $e)
 * {
 *     echo "Validation failed: " . $e->getMessage();
 * }
 * ```
 *
 * Notes:
 * - Both the detected type and the allowed ones are compared by the reference name of their format
 *   ({@see normalizeMimeType()}) : `['audio/wav']` accepts a WAV file that `finfo` reports as
 *   `audio/x-wav`. Only names of the same format meet — an image never passes for a script. The
 *   error message quotes the type as `finfo` reported it.
 * - Allowing `audio/webm` allows a WebM video too : `finfo` reports `video/webm` for both, the
 *   container being the same.
 * - This function uses `mime_content_type()`, which relies on the system's `file` command or magic database.
 * - For consistent results across platforms, ensure the PHP `fileinfo` extension is enabled.
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
function validateMimeType( string $file , array $allowedMimeTypes ): void
{
    // Suppress potential warning if file doesn't exist or cannot be read
    $actualMimeType = @mime_content_type( $file ) ;

    if ( $actualMimeType === false )
    {
        throw new FileException( sprintf('Unable to determine MIME type for file "%s".' , $file ) ) ;
    }

    $normalizedAllowedTypes = [] ;
    foreach ( $allowedMimeTypes as $mimeType )
    {
        if ( is_array( $mimeType ) )
        {
            $normalizedAllowedTypes = array_merge( $normalizedAllowedTypes , $mimeType ) ;
        }
        else
        {
            $normalizedAllowedTypes[] = $mimeType ;
        }
    }

    $normalizedAllowedTypes = array_unique( array_map('strtolower', $normalizedAllowedTypes));

    // Both sides are brought back to the reference name of their format : `audio/wav` accepts a
    // WAV that finfo reports as `audio/x-wav`, and `audio/x-wav` accepts one reported as `audio/wav`.
    $referenceAllowedTypes = array_map( normalizeMimeType( ... ) , $normalizedAllowedTypes ) ;

    if ( !in_array( normalizeMimeType( $actualMimeType ) , $referenceAllowedTypes , true ) )
    {
        throw new FileException( sprintf
        (
            'Invalid MIME type for file "%s". Expected one of [%s], but got "%s".',
            $file,
            implode(', ' , $normalizedAllowedTypes ) ,
            $actualMimeType
        ) ) ;
    }
}