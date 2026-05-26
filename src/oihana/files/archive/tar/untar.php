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
 * @param array{dryRun?: bool, keepPermissions?: bool, overwrite?: bool, maxExtractedSize?: int|null} $options Optional flags:
 *   - **dryRun**: If true, the function does not extract files but returns the list of files
 *     that would be extracted. Default: false.
 *   - **keepPermissions**: If true, preserves the original file permissions from the archive.
 *     Default: false.
 *   - **overwrite**: If false, prevents overwriting existing files during extraction.
 *     Extraction will fail if a file already exists. Default: true.
 *   - **maxExtractedSize**: If set to a positive integer, defines the maximum total uncompressed
 *     size (in bytes) accepted during extraction. The archive is pre-scanned and a
 *     {@see RuntimeException} is thrown **before** any file is written if the sum of the entries'
 *     uncompressed sizes exceeds this limit. Guards against decompression-bomb attacks. Default:
 *     `null` (no limit).
 *
 * @return true|string[] Returns true on successful extraction,
 *                       or an array of file paths (relative to archive root) if dryRun is enabled.
 *
 * @throws FileException If the provided tar file is invalid or inaccessible.
 * @throws DirectoryException If the output directory cannot be created or is not writable.
 * @throws RuntimeException For extraction errors such as:
 *         - Path traversal attempts detected inside archive entries.
 *         - Attempt to overwrite existing files when overwrite is disabled.
 *         - Total uncompressed size exceeds `maxExtractedSize` (decompression-bomb protection).
 *         - Other errors during decompression or extraction.
 *
 * @throws Exception Propagates unexpected exceptions during extraction wrapped as RuntimeException.
 *
 * @example
 * ```php
 * // Basic extraction
 * untar( '/path/to/archive.tar' , '/output/dir' );
 *
 * // Extraction with options
 * untar( '/path/to/archive.tar.gz' , '/output/dir' , [
 * 'overwrite'        => false,
 * 'keepPermissions'  => true
 * ]);
 *
 * // Dry-run: preview contents without extracting
 * $files = untar( '/path/to/archive.tar' , '/output/dir' , [ 'dryRun' => true ] );
 * print_r( $files );
 *
 * // Prevent overwriting, will throw RuntimeException if file exists
 * untar( '/path/to/archive.tar' , '/output/dir' , [ 'overwrite' => false ] );
 * ```
 *
 * @package oihana\files\archive\tar
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
function untar( string $tarFile , string $outputPath , array $options = [] ): true|array
{
    assertTar( $tarFile ) ;
    makeDirectory( $outputPath ) ;

    try
    {
        $dryRun              = $options[ TarOption::DRY_RUN            ] ?? false ;
        $overwrite           = $options[ TarOption::OVERWRITE          ] ?? true  ;
        $preservePermissions = $options[ TarOption::KEEP_PERMISSIONS   ] ?? false ;
        $maxExtractedSize    = $options[ TarOption::MAX_EXTRACTED_SIZE ] ?? null  ;

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

        $sizeLimited = is_int( $maxExtractedSize ) && $maxExtractedSize >= 0 ;

        if ( !$overwrite || $dryRun || $sizeLimited )
        {
            $iterator     = new RecursiveIteratorIterator( $phar ) ;
            $basePharPath = getPharBasePath( $phar ) ;

            $totalSize = 0 ;

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

                if ( $sizeLimited )
                {
                    $totalSize += (int) $file->getSize() ;
                    if ( $totalSize > $maxExtractedSize )
                    {
                        throw new RuntimeException
                        (
                            sprintf
                            (
                                'untar() aborted: extracted size exceeds maximum %d bytes (potential decompression bomb).',
                                $maxExtractedSize
                            )
                        ) ;
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