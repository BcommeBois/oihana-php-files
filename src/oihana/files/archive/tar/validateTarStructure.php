<?php

namespace oihana\files\archive\tar;

use Exception;
use PharData;

/**
 * Validates the internal structure of a tar file.
 *
 * @param string $filePath Path to the tar file.
 * @return bool True if the file has a valid tar structure.
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
