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
 * Extracts a tar archive file into a specified output directory.
 *
 * This function supports regular and compressed tar files (.tar, .tar.gz, .tar.bz2).
 * It can perform a dry run to preview extracted files, optionally preserve file permissions,
 * and control overwriting of existing files.
 *
 * @param string $tarFile Path to the tar archive file to extract.
 * @param string $outputPath Path to the directory where files will be extracted.
 *                           The directory will be created if it does not exist.
 * @param array{dryRun?: bool, keepPermissions?: bool, overwrite?: bool} $options Optional flags:
 *   - **dryRun**: If true, the function does not extract files but returns the list of files
 *     that would be extracted. Default: false.
 *   - **keepPermissions**: If true, preserves the original file permissions from the archive.
 *     Default: false.
 *   - **overwrite**: If false, prevents overwriting existing files during extraction.
 *     Extraction will fail if a file already exists. Default: true.
 *
 * @return true|string[] Returns true on successful extraction,
 *                       or an array of file paths (relative to archive root) if dryRun is enabled.
 *
 * @throws FileException If the provided tar file is invalid or inaccessible.
 * @throws DirectoryException If the output directory cannot be created or is not writable.
 * @throws RuntimeException For extraction errors such as:
 *         - Path traversal attempts detected inside archive entries.
 *         - Attempt to overwrite existing files when overwrite is disabled.
 *         - Other errors during decompression or extraction.
 *
 * @throws Exception Propagates unexpected exceptions during extraction wrapped as RuntimeException.
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