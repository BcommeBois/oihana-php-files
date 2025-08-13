<?php

namespace oihana\files\archive\tar;

use Exception;
use FilesystemIterator;

use oihana\enums\Char;
use Phar;
use PharData;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

use oihana\files\enums\CompressionType;
use oihana\files\enums\TarExtension;
use oihana\files\exceptions\DirectoryException;
use oihana\files\exceptions\FileException;
use oihana\files\exceptions\UnsupportedCompressionException;

use function oihana\reflect\helpers\getFunctionInfo;
use function oihana\files\phar\getPharCompressionType;
use function oihana\files\makeDirectory;

/**
 * Creates a tar archive from one or more files and/or directories.
 *
 * This function supports adding multiple paths (files or directories) to a tar archive,
 * with optional compression (gzip, bzip2, or none).
 * It can preserve the root directory structure inside the archive,
 * and generates a unique temporary archive if no output path is specified.
 *
 * Empty directories are preserved in the archive.
 *
 * @param string|string[] $paths
 *   Absolute path(s) to file(s) or directory(ies) to include in the archive.
 *
 * @param string|null $outputPath
 *   Optional full path to the final archive file to create.
 *   If null, an automatic unique filename with timestamp is generated in the system temp directory.
 *
 * @param string|null $compression
 *   Compression type to use on the tar archive.
 *   Supported values are defined in {@see CompressionType}, defaults to {@see CompressionType::GZIP}.
 *
 * @param string|null $preserveRoot
 *   If set, paths inside the archive will be stored relative to this directory,
 *   allowing to preserve directory structure when extracting.
 *   Must be an absolute path.
 *
 * @return string
 *   Returns the full path to the created tar archive file.
 *
 * @throws FileException
 *   If any of the provided paths does not exist or is invalid.
 *
 * @throws UnsupportedCompressionException
 *   If the requested compression type is not supported by the system.
 *
 * @throws DirectoryException
 *   If the temporary directory cannot be created or accessed.
 *
 * @throws RuntimeException
 *   If no files are added to the archive or if an error occurs during creation,
 *   including inability to rename temporary files.
 *
 * @see CompressionType
 *
 * @example
 * Archive a single file, auto-named, gzip compressed (default):
 * ```php
 * $tarPath = tar('/var/www/html/index.php');
 * ```
 *
 * Archive a directory with bzip2 compression:
 * ```php
 * $tarPath = tar('/var/www/html', '/tmp/site.tar.bz2', CompressionType::BZIP2);
 * ```
 *
 * Archive multiple files with no compression:
 * ```php
 * $tarPath = tar
 * (
 *    ['/etc/hosts', '/etc/hostname'],
 *    '/tmp/config.tar',
 *    CompressionType::NONE
 * );
 * ```
 *
 * Archive directory with root preserved (relative paths):
 * ```php
 * $tarPath = tar
 * (
 *     '/var/www/html/project',
 *     '/tmp/project.tar.gz',
 *     CompressionType::GZIP,
 *     '/var/www/html'
 * );
 * ```
 *
 * @package oihana\files\archive\tar
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
function tar
(
    string|array $paths ,
    ?string $outputPath = null ,
    ?string $compression = CompressionType::GZIP ,
    ?string $preserveRoot = null
)
: string
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
            throw new FileException( sprintf("The path does not exist: %s",  $path ) ) ;
        }
    }

    $tmpPath = getFunctionInfo('oihana\files\archive\tar\tar' )[ 'name' ] ;

    $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . str_replace( Char::BACK_SLASH , DIRECTORY_SEPARATOR , $tmpPath ) . DIRECTORY_SEPARATOR ;

    if( !is_dir( $tmpPath ) )
    {
        makeDirectory( $tmpPath ) ;
    }

    $preserveRootPath = $preserveRoot !== null ? realpath($preserveRoot) : null ;

    if ( $outputPath === null )
    {
        $archiveName    = 'archive_' . date('Ymd_His' ) . uniqid() ;
        $finalExtension = TarExtension::getExtensionForCompression( $compression ) ;
        $finalPath      = $tmpPath . $archiveName . $finalExtension ;
    }
    else
    {
        $finalPath = $outputPath;
    }

    $tempTarPath        = $tmpPath . 'temp_archive_' . uniqid() . TarExtension::TAR ;
    $compressedTempPath = $tempTarPath . TarExtension::getCompressionExtension( $compression ) ;

    try
    {
        $phar       = new PharData( $tempTarPath );
        $hasContent = false;

        foreach ( $paths as $path )
        {
            $realPath = realpath( $path ) ;

            if ( $realPath === false )
            {
                continue;
            }

            if ( is_dir( $realPath ) )
            {
                $directoryIterator = new RecursiveDirectoryIterator ( $realPath, FilesystemIterator::SKIP_DOTS);
                $iterator          = new RecursiveIteratorIterator  ( $directoryIterator , RecursiveIteratorIterator::SELF_FIRST  ) ;
                $emptyDirs         = new RecursiveIteratorIterator  ( $directoryIterator , RecursiveIteratorIterator::CHILD_FIRST ) ;

                foreach ( $emptyDirs as $fileInfo )
                {
                    if ( $fileInfo->isDir() )
                    {
                        $files = scandir( $fileInfo->getPathname() ) ;
                        if (count( $files ) === 2 ) // only "." and ".."
                        {
                            $relativePath = ($preserveRootPath === $realPath)
                                ? $emptyDirs->getSubPathName()
                                : basename( $realPath ) . DIRECTORY_SEPARATOR . $emptyDirs->getSubPathName() ;
                            $phar->addEmptyDir($relativePath);
                        }
                    }
                }

                if ( $preserveRootPath === $realPath )
                {
                    foreach ( $iterator as $item )
                    {
                        $relativePath = $iterator->getSubPathName() ;
                        if ( $item->isDir() )
                        {
                            $phar->addEmptyDir( $relativePath );
                        }
                        else
                        {
                            $phar->addFile( $item->getRealPath(), $relativePath );
                        }
                    }
                }
                else
                {
                    $phar->addEmptyDir( basename( $realPath ) );
                    foreach ( $iterator as $item )
                    {
                        $relativePath = basename( $realPath ) . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
                        if ( $item->isDir() )
                        {
                            $phar->addEmptyDir( $relativePath );
                        }
                        else
                        {
                            $phar->addFile( $item->getRealPath(), $relativePath );
                        }
                    }
                }

                $hasContent = true ;
            }
            else
            {
                $archivePath = ( $preserveRootPath !== null )
                    ? ltrim( str_replace( $preserveRootPath , '' , $realPath ), DIRECTORY_SEPARATOR )
                    : basename( $realPath ) ;

                if ( !empty( $archivePath ) )
                {
                    $phar->addFile( $realPath , $archivePath ) ;
                    $hasContent = true;
                }
            }
        }

        if ( !$hasContent )
        {
            throw new RuntimeException("No files were added to the archive." ) ;
        }

        unset( $phar );

        if ( $compression == CompressionType::NONE )
        {
            rename( $tempTarPath , $finalPath ) ;
        }
        else
        {
            $pharCompression = getPharCompressionType( $compression );

            if ( !Phar::canCompress( $pharCompression ) )
            {
                throw new UnsupportedCompressionException("Compression type '$compression' is not supported.");
            }

            $pharToCompress = new PharData($tempTarPath);

            $pharToCompress->compress($pharCompression);

            unset( $pharToCompress ) ;

            if ( $compressedTempPath !== $finalPath )
            {
                if ( !file_exists( $compressedTempPath ) )
                {
                    throw new RuntimeException("Compressed temporary file was not found at: $compressedTempPath" ) ;
                }
                rename( $compressedTempPath , $finalPath ) ;
            }
        }

        return $finalPath;
    }
    catch ( Exception $exception )
    {
        if ( $exception instanceof FileException || $exception instanceof UnsupportedCompressionException)
        {
            throw $exception ;
        }

        throw new RuntimeException
        (
            "Failed to create archive from paths. Error: " . $exception->getMessage(),
            0,
            $exception
        );
    }
    finally
    {
        if ( file_exists( $tempTarPath ) )
        {
            unlink( $tempTarPath );
        }

        if ( file_exists( $compressedTempPath ) )
        {
            unlink( $compressedTempPath );
        }
    }

}
