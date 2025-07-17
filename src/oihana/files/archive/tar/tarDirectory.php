<?php

namespace oihana\files\archive\tar;

use oihana\files\enums\CompressionType;
use oihana\files\enums\TarExtension;
use oihana\files\enums\TarOption;
use oihana\files\exceptions\DirectoryException;

use oihana\files\exceptions\FileException;
use oihana\files\exceptions\UnsupportedCompressionException;

use RuntimeException;

use function oihana\core\reflections\getFunctionInfo;
use function oihana\files\assertDirectory;
use function oihana\files\copyFilteredFiles;
use function oihana\files\deleteDirectory;
use function oihana\files\makeDirectory;

/**
 * Creates a tar archive from a directory with specified compression.
 *
 * @param string $directory The source directory to archive.
 * @param ?string $compression Compression type (e.g. gzip, bzip2, none).
 * @param string|null $outputPath Optional custom output path. If null, uses directory name with appropriate extension.
 * @param array $options Additional options:
 *      - 'exclude'  => string[] list of glob patterns or file names to exclude
 *      - 'filter'   => callable|null a function (string $filepath): bool
 *      - 'metadata' => array<string, string> additional metadata to include in `.metadata.json`
 *
 * @return string The path to the created archive.
 *
 * @throws DirectoryException If the directory does not exist.
 * @throws FileException
 * @throws UnsupportedCompressionException
 */
function tarDirectory
(
    string $directory ,
    ?string $compression = CompressionType::GZIP ,
    ?string $outputPath  = null ,
    array $options       = []
): string
{
    assertDirectory( $directory ) ;


    if ( $outputPath === null )
    {
        $archiveName = basename( $directory ) ;
        $extension   = TarExtension::getExtensionForCompression( $compression );
        $outputPath  = dirname( $directory ) . DIRECTORY_SEPARATOR . $archiveName . $extension;
    }

    $excludePatterns = $options[ TarOption::EXCLUDE  ] ?? [] ;
    $filterCallback  = $options[ TarOption::FILTER   ] ?? null ;
    $metadata        = $options[ TarOption::METADATA ] ?? [] ;

    if ( empty( $excludePatterns ) && $filterCallback === null && empty( $metadata ) )
    {
        return tar( $directory , $outputPath , $compression , $directory ) ;
    }

    $tmpPath = getFunctionInfo('oihana\files\archive\tar\tarDirectory' )[ 'name' ] ;
    $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . str_replace( "\\", DIRECTORY_SEPARATOR , $tmpPath ) . DIRECTORY_SEPARATOR ;
    $tempDir = $tmpPath . uniqid() ;

    makeDirectory( $tempDir ) ;

    try
    {
        $copiedFiles = copyFilteredFiles($directory, $tempDir, $excludePatterns, $filterCallback);

        if ( !empty( $metadata ) )
        {
            $metaJson = json_encode( $metadata , JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ;
            file_put_contents($tempDir . DIRECTORY_SEPARATOR . '.metadata.json' , $metaJson ) ;
            $copiedFiles = true;
        }

        if ( !$copiedFiles )
        {
            throw new RuntimeException("No files match the filtering criteria." ) ;
        }

        return tar( $tempDir , $outputPath , $compression , $tempDir );
    }
    finally
    {
       deleteDirectory( $tempDir ) ;
    }
}
