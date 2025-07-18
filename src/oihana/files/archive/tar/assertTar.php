<?php

namespace oihana\files\archive\tar;

use oihana\files\exceptions\FileException;

use function oihana\files\assertFile;

/**
 * Validates that a file is a tar archive (compressed or uncompressed).
 *
 * @param string $filePath Path to the file to validate.
 * @param bool $strictMode If true, performs deep validation using file contents.
 *                         If false, only checks extension and basic MIME type.
 *
 * @return bool True if the file is a valid tar archive, false otherwise.
 * @throws FileException If the file does not exist or cannot be read.
 *
 * @example
 * Basic validation using file extension and MIME type:
 * ```php
 * $isValid = isTarFile('/path/to/archive.tar');
 * ```
 * Strict validation with file content inspection:
 * ```php
 * $isValid = isTarFile('/path/to/archive.tar.gz', true);
 * ```
 *
 * Validation failure for non-tar file:
 * ```php
 * $isValid = isTarFile('/path/to/image.jpg');
 * ```
 *
 * @package oihana\files\archive\tar
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
function assertTar( string $filePath , bool $strictMode = false ): bool
{
    assertFile( $filePath );

    if ( !hasTarExtension( $filePath ) )
    {
        return false;
    }

    // Check MIME type
    if ( !hasTarMimeType( $filePath ) )
    {
        return false;
    }

    // Perform deep validation if strict mode is enabled
    if ( $strictMode )
    {
        return validateTarStructure( $filePath );
    }

    return true;
}

