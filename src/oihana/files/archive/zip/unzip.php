<?php

namespace oihana\files\archive\zip;

use ZipArchive;

use oihana\files\enums\ZipOption;
use oihana\files\exceptions\DirectoryException;
use oihana\files\exceptions\FileException;

use function oihana\files\makeDirectory;
use function oihana\files\path\canonicalizePath;
use function oihana\files\path\isBasePath;
use function oihana\files\path\joinPaths;

/**
 * Extracts a zip archive into a destination directory.
 *
 * This function mirrors {@see \oihana\files\archive\tar\untar()}. It guards against path
 * traversal (Zip Slip) and decompression bombs, can preview the contents without writing
 * anything (dry run), and can refuse to overwrite existing files.
 *
 * @param string $zipFile    Path to the zip archive to extract.
 * @param string $outputPath Directory where the archive is extracted. Created if missing.
 * @param array{dryRun?: bool, overwrite?: bool, maxEntries?: int|null, maxSize?: int|null} $options Optional flags, keyed by {@see ZipOption}:
 *   - **dryRun**: If true, no file is written; returns the list of file entries that would be
 *     extracted (directory entries excluded). Default: false.
 *   - **overwrite**: If false, extraction fails when a target file already exists. Default: true.
 *   - **maxEntries**: If a positive integer, the archive is rejected when it declares more
 *     entries than this limit (decompression-bomb guard). Default: null (no limit).
 *   - **maxSize**: If a positive integer, the archive is pre-scanned and rejected **before**
 *     any file is written when the sum of the entries' uncompressed sizes exceeds this limit
 *     (decompression-bomb guard). Default: null (no limit).
 *
 * @return true|string[] Returns true on successful extraction, or the list of file entries
 *                       (relative to the archive root) when dryRun is enabled.
 *
 * @throws FileException If the archive does not exist, cannot be opened, an entry escapes the
 *                       destination (Zip Slip), a bomb guard trips, or a target already exists
 *                       while overwrite is disabled.
 * @throws DirectoryException If the destination directory (or an entry's parent) cannot be created.
 *
 * @example
 * ```php
 * // Basic extraction
 * unzip( '/path/to/archive.zip' , '/output/dir' );
 *
 * // Dry-run: preview contents without extracting
 * $files = unzip( '/path/to/archive.zip' , '/output/dir' , [ 'dryRun' => true ] );
 *
 * // Refuse to overwrite, and guard against decompression bombs
 * unzip( '/path/to/archive.zip' , '/output/dir' , [
 *     'overwrite'  => false,
 *     'maxEntries' => 10_000,
 *     'maxSize'    => 500 * 1024 * 1024,
 * ]);
 * ```
 *
 * @package oihana\files\archive\zip
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.2.0
 */
function unzip( string $zipFile , string $outputPath , array $options = [] ): true|array
{
    assertZip( $zipFile ) ;
    makeDirectory( $outputPath ) ;

    $zip = new ZipArchive() ;

    // @-suppressed: open() emits an E_WARNING on a bad archive; the error is surfaced below.
    if ( @$zip->open( $zipFile ) !== true )
    {
        throw new FileException( sprintf('Cannot open the zip archive "%s".' , $zipFile ) ) ;
    }

    try
    {
        $dryRun     = $options[ ZipOption::DRY_RUN     ] ?? false ;
        $overwrite  = $options[ ZipOption::OVERWRITE   ] ?? true  ;
        $maxEntries = $options[ ZipOption::MAX_ENTRIES ] ?? null  ;
        $maxSize    = $options[ ZipOption::MAX_SIZE    ] ?? null  ;

        $numFiles = $zip->numFiles ;

        if ( is_int( $maxEntries ) && $numFiles > $maxEntries )
        {
            throw new FileException( sprintf('The zip archive has too many entries (%d > %d).' , $numFiles , $maxEntries ) ) ;
        }

        if ( is_int( $maxSize ) && $maxSize >= 0 )
        {
            $total = 0 ;
            for ( $i = 0 ; $i < $numFiles ; $i++ )
            {
                $total += (int) ( $zip->statIndex( $i )[ 'size' ] ?? 0 ) ;
            }

            if ( $total > $maxSize )
            {
                throw new FileException( sprintf('The zip archive exceeds the maximum extracted size (%d > %d bytes).' , $total , $maxSize ) ) ;
            }
        }

        $base    = canonicalizePath( $outputPath ) ;
        $entries = [] ;

        for ( $i = 0 ; $i < $numFiles ; $i++ )
        {
            $name   = $zip->getNameIndex( $i ) ;
            $target = canonicalizePath( joinPaths( $outputPath , $name ) ) ;

            if ( !isBasePath( $base , $target ) )
            {
                throw new FileException( sprintf('Zip Slip detected: the entry "%s" escapes the destination directory.' , $name ) ) ;
            }

            $isDir = str_ends_with( $name , '/' ) ;

            if ( !$isDir )
            {
                $entries[] = $name ;
            }

            if ( $dryRun )
            {
                continue ;
            }

            if ( $isDir )
            {
                makeDirectory( $target ) ;
                continue ;
            }

            if ( !$overwrite && file_exists( $target ) )
            {
                throw new FileException( sprintf('The target file "%s" already exists.' , $target ) ) ;
            }

            makeDirectory( dirname( $target ) ) ;
            file_put_contents( $target , $zip->getFromIndex( $i ) ) ;
        }

        return $dryRun ? $entries : true ;
    }
    finally
    {
        $zip->close() ;
    }
}
