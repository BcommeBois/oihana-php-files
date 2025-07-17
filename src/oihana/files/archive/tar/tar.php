<?php

namespace oihana\files\archive\tar;

use Exception;
use FilesystemIterator;
use oihana\enums\Char;
use oihana\files\enums\CompressionType;
use oihana\files\enums\FileExtension;
use oihana\files\enums\TarExtension;
use oihana\files\exceptions\FileException;

use oihana\files\exceptions\UnsupportedCompressionException;
use Phar;
use PharData;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

use function oihana\files\getPharCompressionType;

/**
 * Creates a tar archive from one or more files and/or directories.
 *
 * @param string|string[] $paths Absolute paths to files or directories to include in the archive.
 * @param string|null $outputPath Optional final output path of the archive. If null, an automatic name is generated.
 * @param string|null $compression Compression type to use (Default : CompressionType::GZIP).
 * @param string|null $preserveRoot If set, all paths in the archive will be stored relative to this directory.
 *                                  Useful to preserve directory structure.
 *
 * @return string The path to the generated archive.
 *
 * @throws FileException If a path does not exist or is invalid.
 * @throws UnsupportedCompressionException
 * @see CompressionType
 */
function tar
(
    string|array $paths ,
    ?string      $outputPath   = null ,
    ?string      $compression  = CompressionType::GZIP ,
    ?string      $preserveRoot = null
)
:string
{
    if ( is_string( $paths ) )
    {
        $paths = [ $paths ] ;
    }

    if ( empty( $paths ) )
    {
        throw new RuntimeException("No input paths provided.") ;
    }

    foreach ( $paths as $path )
    {
        if ( !file_exists( $path ) )
        {
            throw new FileException( sprintf( "The path does not exist: %s" , $path ) ) ;
        }
    }

    $preserveRootPath = $preserveRoot !== null ? realpath( $preserveRoot ) : null;

    $archiveName = $outputPath !== null
        ? pathinfo( $outputPath , PATHINFO_FILENAME )
        : 'archive_' . date('Ymd_His') . uniqid() ;

    $baseTarPath = $compression === CompressionType::NONE
        ? ( $outputPath ?? ( sys_get_temp_dir() . DIRECTORY_SEPARATOR . $archiveName . FileExtension::TAR ) )
        : sys_get_temp_dir() . DIRECTORY_SEPARATOR . $archiveName . FileExtension::TAR ;

    $pharCompression = getPharCompressionType( $compression ) ;

    try
    {
        if( $compression !== CompressionType::NONE && !Phar::canCompress( $pharCompression ) )
        {
            throw new UnsupportedCompressionException("Compression type '$compression' is not supported by this PHP build.") ;
        }

        $phar       = new PharData( $baseTarPath ) ;
        $hasContent = false;

        foreach ( $paths  as $path )
        {
            $realPath = realpath( $path );
            if ( $realPath === false )
            {
                throw new FileException( sprintf("The path is invalid: %s" , $path ) );
            }

            $archivePath = $preserveRootPath !== null
                ? ltrim( str_replace( $preserveRootPath , Char::EMPTY, $realPath ), DIRECTORY_SEPARATOR )
                : basename($realPath);

            if ( is_dir( $realPath ) )
            {
                $files = new RecursiveIteratorIterator
                (
                    new RecursiveDirectoryIterator( $realPath , FilesystemIterator::SKIP_DOTS ) ,
                    RecursiveIteratorIterator::SELF_FIRST
                ) ;

                foreach ( $files as $file )
                {
                    $filePath = $file->getRealPath() ;
                    if ( $filePath === false )
                    {
                        continue;
                    }

                    $relativePath = $preserveRoot !== null
                        ? ltrim( str_replace( realpath($preserveRoot) , Char::EMPTY , $filePath ) , DIRECTORY_SEPARATOR )
                        : ltrim( str_replace( $realPath , $archivePath , $filePath ) , DIRECTORY_SEPARATOR ) ;

                    if ( $relativePath === Char::EMPTY )
                    {
                        continue;
                    }

                    if ( $file->isDir() )
                    {
                        $phar->addEmptyDir( $relativePath ) ;
                    }
                    else
                    {
                        $phar->addFile( $filePath , $relativePath ) ;
                    }

                    $hasContent = true;
                }
            }
            else
            {
                $phar->addFile( $realPath , $archivePath ) ;
                $hasContent = true;
            }
        }

        if ( !$hasContent )
        {
            throw new RuntimeException("No files were added to the archive." ) ;
        }

        if ( $compression !== CompressionType::NONE )
        {
            $phar->compress( $pharCompression ) ;

            unset( $phar ) ;

            $compressedPath = $baseTarPath . TarExtension::getCompressionExtension( $compression );

            if ( $outputPath !== null && $compressedPath !== $outputPath )
            {
                rename( $compressedPath , $outputPath ) ;
            }

            if ( file_exists( $baseTarPath ) )
            {
                unlink( $baseTarPath );
            }

            return $outputPath ?? $compressedPath;
        }

        unset( $phar ) ;

        return $baseTarPath ;
    }
    catch ( Exception $exception )
    {
        throw new RuntimeException
        (
            "Failed to create archive from paths. Error: " . $exception->getMessage() ,
            0 ,
            $exception
        );
    }
}
