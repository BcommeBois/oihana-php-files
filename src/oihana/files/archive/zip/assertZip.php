<?php

namespace oihana\files\archive\zip;

use oihana\files\exceptions\FileException;

use function oihana\files\assertFile;

/**
 * Validates that a file is a zip archive.
 *
 * @param string $filePath   Path to the file to validate.
 * @param bool   $strictMode If true, performs deep validation using file contents
 *                           (see {@see validateZipStructure()}). If false, only checks
 *                           the extension and the basic MIME type.
 *
 * @return bool True if the file is a valid zip archive, false otherwise.
 *
 * @throws FileException If the file does not exist or cannot be read.
 *
 * @package oihana\files\archive\zip
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.2.0
 *
 * @example
 * Basic validation using file extension and MIME type:
 * ```php
 * $isValid = assertZip('/path/to/archive.zip');
 * ```
 * Strict validation with file content inspection:
 * ```php
 * $isValid = assertZip('/path/to/archive.zip', true);
 * ```
 */
function assertZip( string $filePath , bool $strictMode = false ): bool
{
    assertFile( $filePath );

    if ( !hasZipExtension( $filePath ) )
    {
        return false;
    }

    if ( !hasZipMimeType( $filePath ) )
    {
        return false;
    }

    if ( $strictMode )
    {
        return validateZipStructure( $filePath );
    }

    return true;
}
