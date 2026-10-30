<?php

namespace oihana\files\archive\tar;

use Exception;

use oihana\enums\Char;
use Phar;
use PharData;

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

    $compression ??= CompressionType::GZIP ;

    // Refused here, by name, and about this host rather than about this library.
    //
    // This used to come out of TarExtension::getCompressionExtension() while it assembled
    // a temporary path for the PharData branch — seventeen lines before the engines had
    // been looked at, so "Compression type 'xz' is not supported" was said of a library
    // whose system tar could write it perfectly well. What varies is the machine: PharData
    // offers gzip and bzip2 through PHP extensions, `tar` offers whatever program it finds.

    $writable = tarWritableCompressions() ;

    if ( !in_array( $compression , $writable , true ) )
    {
        throw new UnsupportedCompressionException( sprintf
        (
            in_array( $compression , [ CompressionType::XZ , CompressionType::ZSTD ] , true )
                ? '%1$s needs the system GNU tar and its %1$s program, and one of them is missing here. This host writes: %2$s.'
                : 'tar() does not write %1$s archives. This host writes: %2$s.' ,
            $compression ,
            implode( ', ' , $writable )
        )) ;
    }

    $tmpPath = getFunctionInfo('oihana\files\archive\tar\tar' )[ 'name' ] ;

    $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . str_replace( Char::BACK_SLASH , DIRECTORY_SEPARATOR , $tmpPath ) . DIRECTORY_SEPARATOR ;

    if( !is_dir( $tmpPath ) )
    {
        makeDirectory( $tmpPath ) ;
    }

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

    $tempTarPath = $tmpPath . 'temp_archive_' . uniqid() . TarExtension::TAR ;

    // What the archive is to hold, and under which names. Computed once, for whichever engine
    // ends up writing it: the names are the compatibility contract with every archive already
    // written, so they cannot be worked out twice and hope to stay the same.

    $entries = tarEntries( $paths , $preserveRoot ) ;

    // The system tar, when there is one it can trust. PharData writes tar archives in pure PHP
    // and on a real tree it is not viable: 317 seconds against 1.63 on the same 96 MB, for the
    // same 17 MB archive, and it refuses outright any path component past the 100-byte ustar
    // limit — which one file of a stock WordPress plugin set is enough to hit.
    //
    // The choice is made here, before anything is written, and is not revisited afterwards.

    $binary = tarBinary() ;

    if ( $binary !== null && tarBinaryHandles( $entries , $compression ) )
    {
        tarWithBinary( $binary , $entries , $finalPath , $compression ) ;

        return $finalPath ;
    }

    // PharData is the writer from here on, and it cannot store a symbolic link: Phar exposes no
    // way to write one — there is no addSymlink, and the ustar type flag is out of reach of the
    // API. Rather than store something else in its place, the tree is read for links before
    // anything is written, so the refusal names the link and leaves no half-written archive.

    foreach ( $entries as $entry )
    {
        if ( is_link( $entry[ 'path' ] ) )
        {
            throw new RuntimeException( sprintf
            (
                'Cannot archive the symbolic link "%s": this archive is being written by PharData, which cannot store a link entry. Install the system GNU tar, or archive the paths one at a time so that it can be used, or use zip() — all three keep the link as a link.' ,
                $entry[ 'path' ]
            ) ) ;
        }
    }

    // Only the PharData branch writes a compressed temporary file, and only it knows the
    // extension of one — which is why this is computed here and not above. Asked earlier,
    // for a compression PharData cannot write, it refused an archive the binary was about
    // to produce.

    $compressedTempPath = $tempTarPath . TarExtension::getCompressionExtension( $compression ) ;

    try
    {
        $phar       = new PharData( $tempTarPath );
        $hasContent = false;

        foreach ( $entries as $entry )
        {
            if ( $entry[ 'directory' ] )
            {
                $phar->addEmptyDir( $entry[ 'name' ] ) ;
            }
            else
            {
                $phar->addFile( $entry[ 'path' ] , $entry[ 'name' ] ) ;
            }

            $hasContent = true ;
        }

        if ( !$hasContent )
        {
            throw new RuntimeException("No files were added to the archive." ) ;
        }

        unset( $phar );

        if ( $compression == CompressionType::NONE )
        {
            // @-suppressed: rename() warns on a failure the exception below reports in full.
            // Unchecked, tar() returned the path of an archive that was never moved there.

            if ( !@rename( $tempTarPath , $finalPath ) )
            {
                throw new FileException( sprintf('Cannot move the archive to "%s".' , $finalPath ) ) ;
            }
        }
        else
        {
            $pharCompression = getPharCompressionType( $compression );

            if ( !Phar::canCompress( $pharCompression ) )
            {
                // Unreachable here: GZIP/BZIP2 are always compressible (ext-zlib/bz2 present);
                // this guards runtimes that lack the compression extension.
                // @codeCoverageIgnoreStart
                throw new UnsupportedCompressionException("Compression type '$compression' is not supported.");
                // @codeCoverageIgnoreEnd
            }

            $pharToCompress = new PharData($tempTarPath);

            $pharToCompress->compress($pharCompression);

            unset( $pharToCompress ) ;

            if ( $compressedTempPath !== $finalPath )
            {
                if ( !file_exists( $compressedTempPath ) )
                {
                    // Unreachable: a successful compress() always produces this sibling file.
                    // @codeCoverageIgnoreStart
                    throw new RuntimeException("Compressed temporary file was not found at: $compressedTempPath" ) ;
                    // @codeCoverageIgnoreEnd
                }
                if ( !@rename( $compressedTempPath , $finalPath ) )
                {
                    throw new FileException( sprintf('Cannot move the compressed archive to "%s".' , $finalPath ) ) ;
                }
            }
        }

        return $finalPath;
    }
    // Defensive wrapper: the typed exceptions (Unsupported/FileException) are thrown
    // *before* this try (lines ~147/156), and no \Exception is deterministically raised
    // inside it, so this catch is unreachable under test.
    // @codeCoverageIgnoreStart
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
    // @codeCoverageIgnoreEnd
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
