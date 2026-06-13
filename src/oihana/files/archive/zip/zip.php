<?php

namespace oihana\files\archive\zip;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

use oihana\files\enums\CompressionType;
use oihana\files\enums\FileExtension;
use oihana\files\exceptions\DirectoryException;
use oihana\files\exceptions\FileException;
use oihana\files\exceptions\UnsupportedCompressionException;

use function oihana\reflect\helpers\getFunctionInfo;
use function oihana\files\makeDirectory;

/**
 * Creates a zip archive from one or more files and/or directories.
 *
 * This function supports adding multiple paths (files or directories) to a zip archive,
 * with a per-entry compression method. It can preserve the root directory structure
 * inside the archive, and generates a unique temporary archive if no output path is
 * specified.
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
 *   Per-entry compression method. Supported values are {@see CompressionType::ZIP}
 *   (DEFLATE, the default) and {@see CompressionType::NONE} (stored, no compression).
 *
 * @param string|null $preserveRoot
 *   If set, paths inside the archive will be stored relative to this directory,
 *   allowing to preserve directory structure when extracting. Must be an absolute path.
 *
 * @return string
 *   Returns the full path to the created zip archive file.
 *
 * @throws FileException
 *   If any of the provided paths does not exist, or if the archive file cannot be created.
 *
 * @throws UnsupportedCompressionException
 *   If the requested compression method is not supported for zip archives.
 *
 * @throws DirectoryException
 *   If the temporary directory cannot be created or accessed.
 *
 * @throws RuntimeException
 *   If no files are added to the archive.
 *
 * @see CompressionType
 *
 * @example
 * Archive a single file, auto-named, DEFLATE compressed (default):
 * ```php
 * $zipPath = zip('/var/www/html/index.php');
 * ```
 *
 * Archive a directory without compression (stored):
 * ```php
 * $zipPath = zip('/var/www/html', '/tmp/site.zip', CompressionType::NONE);
 * ```
 *
 * Archive multiple files:
 * ```php
 * $zipPath = zip(['/etc/hosts', '/etc/hostname'], '/tmp/config.zip');
 * ```
 *
 * Archive directory with root preserved (relative paths):
 * ```php
 * $zipPath = zip('/var/www/html/project', '/tmp/project.zip', CompressionType::ZIP, '/var/www/html/project');
 * ```
 *
 * @package oihana\files\archive\zip
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.2.0
 */
function zip
(
    string|array $paths ,
    ?string $outputPath = null ,
    ?string $compression = CompressionType::ZIP ,
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

    $method = match ( $compression )
    {
        CompressionType::ZIP  => ZipArchive::CM_DEFLATE ,
        CompressionType::NONE => ZipArchive::CM_STORE ,
        default               => throw new UnsupportedCompressionException( sprintf( "Compression type '%s' is not supported", $compression ) )
    } ;

    if ( $outputPath === null )
    {
        $tmpPath = getFunctionInfo('oihana\files\archive\zip\zip' )[ 'name' ] ;
        $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . str_replace( '\\' , DIRECTORY_SEPARATOR , $tmpPath ) . DIRECTORY_SEPARATOR ;

        if ( !is_dir( $tmpPath ) )
        {
            makeDirectory( $tmpPath ) ;
        }

        $archiveName = 'archive_' . date('Ymd_His' ) . uniqid() ;
        $finalPath   = $tmpPath . $archiveName . FileExtension::ZIP ;
    }
    else
    {
        $finalPath = $outputPath ;
    }

    $preserveRootPath = $preserveRoot !== null ? realpath( $preserveRoot ) : null ;

    $zip = new ZipArchive() ;

    if ( $zip->open( $finalPath , ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true )
    {
        throw new FileException( sprintf('Cannot create the zip archive "%s".' , $finalPath ) ) ;
    }

    $entries    = [] ; // file entry names, used to apply the compression method
    $hasContent = false ;

    foreach ( $paths as $path )
    {
        $realPath = realpath( $path ) ;

        if ( $realPath === false )
        {
            // Unreachable: every $path was already proven to exist (file_exists guard above).
            // @codeCoverageIgnoreStart
            continue;
            // @codeCoverageIgnoreEnd
        }

        if ( is_dir( $realPath ) )
        {
            $directoryIterator = new RecursiveDirectoryIterator( $realPath , FilesystemIterator::SKIP_DOTS ) ;
            $iterator          = new RecursiveIteratorIterator( $directoryIterator , RecursiveIteratorIterator::SELF_FIRST ) ;

            if ( $preserveRootPath === $realPath )
            {
                foreach ( $iterator as $item )
                {
                    $relativePath = $iterator->getSubPathName() ;
                    if ( $item->isDir() )
                    {
                        $zip->addEmptyDir( $relativePath ) ;
                    }
                    else
                    {
                        $zip->addFile( $item->getRealPath() , $relativePath ) ;
                        $entries[] = $relativePath ;
                    }
                }
            }
            else
            {
                $zip->addEmptyDir( basename( $realPath ) ) ;
                foreach ( $iterator as $item )
                {
                    $relativePath = basename( $realPath ) . DIRECTORY_SEPARATOR . $iterator->getSubPathName() ;
                    if ( $item->isDir() )
                    {
                        $zip->addEmptyDir( $relativePath ) ;
                    }
                    else
                    {
                        $zip->addFile( $item->getRealPath() , $relativePath ) ;
                        $entries[] = $relativePath ;
                    }
                }
            }

            $hasContent = true ;
        }
        else
        {
            $archivePath = ( $preserveRootPath !== null )
                ? ltrim( str_replace( $preserveRootPath , '' , $realPath ) , DIRECTORY_SEPARATOR )
                : basename( $realPath ) ;

            if ( !empty( $archivePath ) )
            {
                $zip->addFile( $realPath , $archivePath ) ;
                $entries[]  = $archivePath ;
                $hasContent = true ;
            }
        }
    }

    if ( !$hasContent )
    {
        // @-suppressed: closing an archive with no committed entry returns false on some libzip builds.
        @$zip->close() ;
        throw new RuntimeException("No files were added to the archive." ) ;
    }

    if ( $method === ZipArchive::CM_STORE )
    {
        foreach ( $entries as $name )
        {
            $zip->setCompressionName( $name , ZipArchive::CM_STORE ) ;
        }
    }

    // @-suppressed: close() emits an E_WARNING when the archive cannot be flushed to disk
    // (e.g. a missing parent directory); the failure is surfaced by the file_exists() guard below.
    @$zip->close() ;

    if ( !file_exists( $finalPath ) )
    {
        throw new FileException( sprintf('Cannot create the zip archive "%s".' , $finalPath ) ) ;
    }

    return $finalPath ;
}
