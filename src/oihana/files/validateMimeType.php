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
 */
function validateMimeType( string $file , array $allowedMimeTypes ): void
{
    // Suppress potential warning if file doesn't exist or cannot be read
    $actualMimeType = @mime_content_type( $file ) ;

    if ( $actualMimeType === false )
    {
        throw new FileException(sprintf('Unable to determine MIME type for file "%s".' , $file ) ) ;
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
    $actualMimeTypeLower    = strtolower( $actualMimeType ) ;

    // Check if the actual MIME type is in the allowed list
    if ( !in_array( $actualMimeTypeLower , $normalizedAllowedTypes , true ) )
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