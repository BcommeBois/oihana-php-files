<?php

namespace oihana\files\phar ;

use Exception;
use PharData;

/**
 * Preserves file permissions from the archive to the extracted files.
 *
 * @param PharData $phar The PharData instance.
 * @param string $outputPath The extraction output path.
 *
 * @return void
 */
function preservePharFilePermissions( PharData $phar , string $outputPath ): void
{
    try
    {
        foreach ( $phar as $file )
        {
            $filePath = $outputPath . DIRECTORY_SEPARATOR . $file->getFilename();

            if ( file_exists( $filePath ) )
            {
                $permissions = $file->getPerms() ;
                if ( $permissions !== false )
                {
                    chmod( $filePath , $permissions ) ;
                }
            }
        }
    }
    catch ( Exception $exception )
    {
        error_log( sprintf( 'Warning: Could not preserve permissions for some files: %s' , $exception->getMessage() ) );
    }
}