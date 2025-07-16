<?php

namespace oihana\files\archive;

use Exception;
use PharData;
use RuntimeException;

use oihana\files\enums\TarOption;
use oihana\files\exceptions\DirectoryException;
use oihana\files\exceptions\FileException;

use function oihana\files\makeDirectory;
use function oihana\files\phar\preservePharFilePermissions;


/**
 * Extracts a tar file into a directory path.
 *
 * @param string $tarFile The tar file to extract.
 * @param string $outputPath The path of the output directory to extract to.
 * @param array{ keepPermissions:bool , overwrite:bool } $options Additional options:
 * <ul>
 * <li>**overwrite** => Whether to overwrite existing files - Default: true</li>
 * <li>**keepPermissions** => Whether to preserve file permissions - Default: false</li>
 *</ul>
 *
 * @return bool true
 * @throws FileException
 * @throws RuntimeException
 * @throws DirectoryException
 * @throws FileException
 * @throws DirectoryException
 */
function untar( string $tarFile , string $outputPath , array $options = [] ): true
{
    assertTar( $tarFile ) ;

    makeDirectory( $outputPath ) ;

    $overwrite           = $options[ TarOption::OVERWRITE        ] ?? true  ;
    $preservePermissions = $options[ TarOption::KEEP_PERMISSIONS ] ?? false ;

    try
    {
        $phar = new PharData( $tarFile );

        $phar->extractTo( $outputPath , null , $overwrite );

        if ( $preservePermissions )
        {
            preservePharFilePermissions( $phar , $outputPath );
        }

        return true;
    }
    catch ( Exception $exception )
    {
        throw new RuntimeException
        (
            sprintf
            (
                'Failed to extract tar file %s to directory %s. Error: %s',
                json_encode( $tarFile , JSON_UNESCAPED_SLASHES ),
                json_encode( $outputPath , JSON_UNESCAPED_SLASHES ),
                $exception->getMessage()
            ) ,
            0 , $exception
        );
    }
}