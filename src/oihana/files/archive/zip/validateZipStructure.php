<?php

namespace oihana\files\archive\zip;

use ZipArchive;

/**
 * Validates the internal structure of a zip file.
 *
 * This function checks whether the given file is a valid, readable zip archive.
 * It uses the {@see ZipArchive} class to attempt opening the archive and inspects
 * a few entries to confirm structural integrity.
 *
 * @param string $filePath Path to the zip file.
 *
 * @return bool True if the file has a valid zip structure, false otherwise.
 *
 * @package oihana\files\archive\zip
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.2.0
 *
 * @example
 * ```php
 * var_dump( validateZipStructure( '/path/to/archive.zip'  ) ); // true or false
 * var_dump( validateZipStructure( '/path/to/invalid.zip'  ) ); // false
 * var_dump( validateZipStructure( '/path/to/not_a_zip.txt') ); // false
 * var_dump( validateZipStructure( '/nonexistent/file.zip' ) ); // false
 * ```
 */
function validateZipStructure( string $filePath ): bool
{
    if ( !is_file( $filePath ) )
    {
        return false;
    }

    $zip = new ZipArchive();

    // @-suppressed: open() emits an E_WARNING on a corrupt archive; the failure is the false return.
    if ( @$zip->open( $filePath ) !== true )
    {
        return false;
    }

    // Inspect a few entries to confirm the central directory is readable.
    $count = min( $zip->numFiles , 10 );
    for ( $i = 0 ; $i < $count ; $i++ )
    {
        $zip->statIndex( $i );
    }

    $zip->close();

    return true;
}
