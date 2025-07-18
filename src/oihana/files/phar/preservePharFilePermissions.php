<?php

namespace oihana\files\phar ;

use Exception;
use PharData;

/**
 * Preserves file permissions from a Phar archive to the extracted files.
 *
 * This function iterates over the contents of a `PharData` archive and applies
 * the original file permissions (as stored in the archive) to the corresponding
 * extracted files in the specified output directory.
 *
 * This is especially useful when extracting `.tar` or `.tar.gz` archives where
 * file modes (e.g., executable bits) should be retained.
 *
 * If a file's permissions cannot be set, the function logs a warning using `error_log()`.
 *
 * @param PharData $phar       The PharData archive instance.
 * @param string   $outputPath The absolute path to the directory where files were extracted.
 *
 * @return void
 *
 * @package oihana\files\phar
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 *
 * @example
 * ```php
 * use PharData;
 * use oihana\files\phar\assertPhar;
 * use oihana\files\phar\preservePharFilePermissions;
 *
 * assertPhar();
 *
 * $phar = new PharData('/archives/app.tar');
 * $phar->extractTo('/var/www/app', true);
 *
 * preservePharFilePermissions($phar, '/var/www/app');
 * ```
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