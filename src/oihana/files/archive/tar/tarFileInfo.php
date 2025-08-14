<?php

namespace oihana\files\archive\tar;

use Exception;
use oihana\files\enums\CompressionType;
use oihana\files\enums\TarInfo;
use oihana\files\exceptions\FileException;
use PharData;

use function oihana\files\assertFile;

/**
 * Retrieves detailed information about a tar archive file.
 *
 * This function inspects the given tar file to determine its validity,
 * compression type, MIME type, number of contained files, and total size of the contents.
 *
 * It uses the {@see PharData} class to count files and calculate total size when the tar is valid.
 *
 * @param string $filePath
 *   Absolute path to the tar archive file to inspect.
 *
 * @param bool $strictMode
 *   When true, enables strict validation of the tar file structure via {@see assertTar()}.
 *   Default is false for a more lenient check.
 *
 * @return array{
 *   is_valid?    : bool,
 *   extension?   : string,
 *   mime_type?   : string|null,
 *   compression? : string|null,
 *   file_count?  : int|null,
 *   total_size?  : int|null
 * }
 *   Returns an associative array with:
 *   - **isValid**: Whether the tar file is valid according to {@see assertTar()}.
 *   - **extension**: File extension (lowercase) extracted from the path.
 *   - **mimeType**: MIME type detected via `finfo`.
 *   - **compression**: Compression type detected (gzip, bzip2, or none).
 *   - **fileCount**: Number of files inside the tar (if valid), otherwise null.
 *   - **totalSize**: Sum of sizes (in bytes) of all files inside (if valid), otherwise null.
 *
 * @throws FileException
 *   If the provided file does not exist or is not accessible.
 *
 * @see assertTar()
 *
 * @example
 * ```php
 * $info = tarFileInfo( '/archives/sample.tar' );
 * print_r( $info );
 *
 * $info = tarFileInfo( '/archives/compressed.tar.gz' );
 * echo $info['compression']; // 'gzip'
 *
 * $info = tarFileInfo( '/bad/path.tar' );
 * var_dump( $info['isValid'] ); // false
 *
 * // Strict mode
 * $info = tarFileInfo( '/archives/sample.tar' , true );
 * ```
 *
 * @package oihana\files\archive\tar
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
function tarFileInfo( string $filePath , bool $strictMode = false ): array
{
    assertFile( $filePath );

    $info =
    [
        TarInfo::IS_VALID    => false ,
        TarInfo::EXTENSION   => strtolower( pathinfo( $filePath , PATHINFO_EXTENSION ) ) ,
        TarInfo::MIME_TYPE   => null ,
        TarInfo::COMPRESSION => null ,
        TarInfo::FILE_COUNT  => null ,
        TarInfo::TOTAL_SIZE  => null
    ];

    // Get MIME type
    $fileInfo = finfo_open( FILEINFO_MIME_TYPE );
    if ( $fileInfo !== false )
    {
        $mimeType = finfo_file( $fileInfo , $filePath) ?: 'unknown';
        finfo_close( $fileInfo );
        $info[TarInfo::MIME_TYPE] = $mimeType;
    }

    // Determine compression type
    if ( str_contains( $info[ TarInfo::MIME_TYPE ] , CompressionType::GZIP ) )
    {
        $info[ TarInfo::COMPRESSION ] = CompressionType::GZIP ;
    }
    elseif ( str_contains( $info[ TarInfo::MIME_TYPE ] , CompressionType::BZIP2 ) )
    {
        $info[ TarInfo::COMPRESSION ] = CompressionType::BZIP2 ;
    }
    else
    {
        $info[ TarInfo::COMPRESSION ] = CompressionType::NONE ;
    }

    // Validate and get archive details
    if ( assertTar( $filePath , $strictMode ) )
    {
        $info[ TarInfo::IS_VALID ] = true ;

        try
        {
            $phar = new PharData( $filePath );
            $info[ TarInfo::FILE_COUNT ] = count( $phar );

            $totalSize = 0;
            foreach ( $phar as $file )
            {
                $totalSize += $file->getSize();
            }
            $info[ TarInfo::TOTAL_SIZE ] = $totalSize;
        }
        catch ( Exception $e )
        {
            // Keep is_valid as true but don't populate detailed info
        }
    }

    return $info;
}
