<?php

namespace oihana\files\archive\tar;

use Exception;
use oihana\files\enums\CompressionType;
use oihana\files\enums\TarInfo;
use oihana\files\exceptions\FileException;
use PharData;
use function oihana\files\assertFile;

/**
 * Gets detailed information about a tar file.
 *
 * @param string $filePath Path to the tar file.
 * @param bool $strictMode Indicates if the validation of the tar file is strict or not.
 *
 * @return array Information about the tar file including:
 *               - 'is_valid' => bool
 *               - 'extension' => string
 *               - 'mime_type' => string
 *               - 'compression' => string|null
 *               - 'file_count' => int|null (only if valid)
 *               - 'total_size' => int|null (only if valid)
 * @throws FileException If the file does not exist.
 * @see assertTar()
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
