<?php

namespace oihana\files\archive\tar;

use Exception;
use oihana\enums\Char;
use PharData;
use RecursiveIteratorIterator;
use RuntimeException;

use oihana\files\enums\TarOption;
use oihana\files\exceptions\DirectoryException;
use oihana\files\exceptions\FileException;

use function oihana\files\makeDirectory;
use function oihana\files\phar\getPharBasePath;
use function oihana\files\phar\preservePharFilePermissions;

/**
 * Extracts a tar file into a directory path.
 *
 * @param string $tarFile The tar file to extract.
 * @param string $outputPath The path of the output directory to extract to.
 * @param array{ dryRun?: bool , keepPermissions?:bool , overwrite?:bool } $options Additional options:
 * <ul>
 * <li>**overwrite** => Whether to overwrite existing files - Default: true</li>
 * <li>**keepPermissions** => Whether to preserve file permissions - Default: false</li>
 *</ul>
 *
 * @return true|array true|string[] Returns true on extraction, or list of files in dryRun mode.
 * @throws FileException
 * @throws RuntimeException
 * @throws DirectoryException
 */
function untar( string $tarFile , string $outputPath , array $options = [] ): true|array
{
    assertTar( $tarFile ) ;
    makeDirectory( $outputPath ) ;

    try
    {
        $dryRun              = $options[ TarOption::DRY_RUN          ] ?? false ;
        $overwrite           = $options[ TarOption::OVERWRITE        ] ?? true  ;
        $preservePermissions = $options[ TarOption::KEEP_PERMISSIONS ] ?? false ;

        $isCompressed = tarIsCompressed( $tarFile ) ;

        $phar = new PharData( $tarFile );

        $decompressedPath = null ;

        if ( $isCompressed )
        {
            $phar = $phar->decompress() ;
            $decompressedPath = $phar->getPath();
        }

        assertTar( $phar->getPath() ) ;

        $fileList = [] ;

        if ( !$overwrite || $dryRun )
        {
            $iterator     = new RecursiveIteratorIterator( $phar ) ;
            $basePharPath = getPharBasePath( $phar ) ;

            foreach ( $iterator as $file )
            {
                if ( !$file->isFile() )
                {
                    continue;
                }

                $relativePath = str_replace($basePharPath . '/', '', $file->getPathname() ) ;

                if ( str_contains( $relativePath , Char::DOUBLE_DOT ) )
                {
                    throw new RuntimeException( sprintf( 'Path traversal attempt detected in tar file: %s', $relativePath ) );
                }

                $fileList[] = $relativePath ;

                if ( !$overwrite )
                {
                    $targetPath = $outputPath . DIRECTORY_SEPARATOR . $relativePath;
                    if ( file_exists( $targetPath ) )
                    {
                        throw new RuntimeException( sprintf( 'untar() failed, the file %s already exists and overwrite is disabled.', $targetPath ) ) ;
                    }
                }
            }
        }

        if ( $dryRun )
        {
            if ( $decompressedPath && file_exists( $decompressedPath ) )
            {
                unlink( $decompressedPath ) ;
            }

            return $fileList;
        }

        $phar->extractTo( $outputPath , null , $overwrite );

        if ( $preservePermissions )
        {
            preservePharFilePermissions( $phar , $outputPath );
        }

        // Clean up extracted .tar if original was compressed
        if ( $decompressedPath && file_exists( $decompressedPath ) )
        {
            unlink( $decompressedPath ) ;
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