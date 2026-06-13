<?php

namespace oihana\files\archive\zip;

use oihana\files\enums\CompressionType;
use oihana\files\enums\ZipInfo;
use oihana\files\exceptions\FileException;
use ZipArchive;

use function oihana\files\assertFile;
use function oihana\files\getMimeType;

/**
 * Retrieves detailed information about a zip archive file.
 *
 * This function inspects the given zip file to determine its validity, MIME type,
 * compression family, number of contained entries, and total uncompressed size.
 *
 * It uses the {@see ZipArchive} class to count entries and sum their uncompressed sizes
 * when the archive is valid.
 *
 * @param string $filePath
 *   Absolute path to the zip archive file to inspect.
 *
 * @param bool $strictMode
 *   When true, enables strict validation of the zip structure via {@see assertZip()}.
 *   Default is false for a more lenient check.
 *
 * @return array{
 *   isValid?     : bool,
 *   extension?   : string,
 *   mimeType?    : string|null,
 *   compression? : string|null,
 *   fileCount?   : int|null,
 *   totalSize?   : int|null
 * }
 *   Returns an associative array keyed by {@see ZipInfo} with:
 *   - **isValid**: Whether the file is a valid zip according to {@see assertZip()}.
 *   - **extension**: File extension (lowercase) extracted from the path.
 *   - **mimeType**: MIME type detected via `finfo`.
 *   - **compression**: {@see CompressionType::ZIP} when the MIME type is zip-like, otherwise {@see CompressionType::NONE}.
 *   - **fileCount**: Number of entries inside the archive (if valid), otherwise null.
 *   - **totalSize**: Sum of the uncompressed sizes (in bytes) of all entries (if valid), otherwise null.
 *
 * @throws FileException
 *   If the provided file does not exist or is not accessible.
 *
 * @see assertZip()
 *
 * @package oihana\files\archive\zip
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.2.0
 *
 * @example
 * ```php
 * $info = zipFileInfo( '/archives/sample.zip' );
 * print_r( $info );
 *
 * $info = zipFileInfo( '/bad/path.zip' );
 * var_dump( $info['isValid'] ); // false
 *
 * // Strict mode
 * $info = zipFileInfo( '/archives/sample.zip' , true );
 * ```
 */
function zipFileInfo( string $filePath , bool $strictMode = false ): array
{
    assertFile( $filePath );

    $info =
    [
        ZipInfo::IS_VALID    => false ,
        ZipInfo::EXTENSION   => strtolower( pathinfo( $filePath , PATHINFO_EXTENSION ) ) ,
        ZipInfo::MIME_TYPE   => null ,
        ZipInfo::COMPRESSION => null ,
        ZipInfo::FILE_COUNT  => null ,
        ZipInfo::TOTAL_SIZE  => null
    ];

    // Get MIME type
    $info[ ZipInfo::MIME_TYPE ] = getMimeType( $filePath ) ?? 'unknown' ;

    // Determine compression family from the MIME type
    $info[ ZipInfo::COMPRESSION ] = str_contains( (string) $info[ ZipInfo::MIME_TYPE ] , CompressionType::ZIP )
        ? CompressionType::ZIP
        : CompressionType::NONE ;

    // Validate and get archive details
    if ( assertZip( $filePath , $strictMode ) )
    {
        $info[ ZipInfo::IS_VALID ] = true ;

        $zip = new ZipArchive();
        if ( @$zip->open( $filePath ) === true )
        {
            $info[ ZipInfo::FILE_COUNT ] = $zip->numFiles ;

            $totalSize = 0;
            for ( $i = 0 ; $i < $zip->numFiles ; $i++ )
            {
                $totalSize += (int) ( $zip->statIndex( $i )[ 'size' ] ?? 0 ) ;
            }
            $info[ ZipInfo::TOTAL_SIZE ] = $totalSize ;

            $zip->close();
        }
    }

    return $info;
}
