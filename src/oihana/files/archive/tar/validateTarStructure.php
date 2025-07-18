<?php

namespace oihana\files\archive\tar;

use Exception;
use PharData;

/**
 * Validates the internal structure of a tar file.
 *
 * This function checks whether the given file is a valid, readable tar archive.
 * It uses the `PharData` class to attempt parsing the archive and iterates over
 * a few entries to confirm structural integrity.
 *
 * Note: Compressed tar files (e.g., `.tar.gz`, `.tar.bz2`) are not supported directly.
 * Decompress them before using this function.
 *
 * @param string $filePath Path to the tar file.
 *
 * @return bool True if the file has a valid tar structure, false otherwise.
 *
 * @example
 * ```php
 * var_dump( validateTarStructure( '/path/to/archive.tar'     ) ); // true or false
 * var_dump( validateTarStructure( '/path/to/invalid.tar'     ) ); // false
 * var_dump( validateTarStructure( '/path/to/archive.tar.gz'  ) ); // false (must decompress first)
 * var_dump( validateTarStructure( '/path/to/not_a_tar.txt'   ) ); // false
 * var_dump( validateTarStructure( '/nonexistent/file.tar'    ) ); // false
 * ```
 *
 * @package oihana\files\archive\tar
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
function validateTarStructure( string $filePath ): bool
{
    if ( !is_file( $filePath ) )
    {
        return false;
    }

    try
    {
        $phar = new PharData( $filePath );

        // Try to iterate through the archive
        $count = 0;
        foreach ( $phar as $file )
        {
            $count++;
            // Stop after checking a few files to avoid performance issues
            if ( $count > 10 )
            {
                break;
            }
        }

        return true;
    }
    catch ( Exception $e )
    {
        // If we can't read it as a PharData, it's not a valid tar
        return false;
    }
}
