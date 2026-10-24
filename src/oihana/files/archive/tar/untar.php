<?php

namespace oihana\files\archive\tar;

use Exception;
use PharData;
use RecursiveIteratorIterator;
use RuntimeException;

use oihana\files\enums\CompressionType;
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
 * ### Which engine extracts
 *
 * The system `tar` when there is a GNU one and the decompressor it needs
 * ({@see untarBinaryHandles()}), PharData otherwise. The choice is made **before
 * anything is written** and is not revisited, as it is for writing in {@see tar()}.
 *
 * It is not a question of speed: PharData extracts a plain archive at a comparable pace.
 * It is that decompressing with PharData means having PharData **write** a new `.tar`
 * first, and its writer refuses any path component over 100 bytes — the `ustar` limit.
 * Since 1.3.0 `tar()` delegates to GNU tar, which stores such names without difficulty,
 * so a `.tar.gz` this library wrote could fail to extract through this one. A stock
 * WordPress carries such a path. The rebuild also costs about twice the archive in
 * memory, and an uncompressed copy on disk beside it.
 *
 * Both engines honour the four options below and produce the same tree, which is why
 * the guards live in one place, {@see untarAssertExtractable()}.
 *
 * ### Extracting the same archive twice, on the PharData path
 *
 * It used to fail, with *"a phar with that name already exists"*: `PharData::decompress()`
 * writes a `.tar` named after the archive and registers it in an internal list that
 * removing the file does not clear. `Phar::unlinkArchive()` clears both and corrupted the
 * Zend heap when called from here, so the collision is avoided instead:
 * {@see untarUniqueAlias()} gives the archive a uniquely named hard link — no bytes
 * copied — and the `.tar` inherits that name. Both go at the end.
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

    $dryRun              = $options[ TarOption::DRY_RUN            ] ?? false ;
    $overwrite           = $options[ TarOption::OVERWRITE          ] ?? true  ;
    $preservePermissions = $options[ TarOption::KEEP_PERMISSIONS   ] ?? false ;
    $maxExtractedSize    = $options[ TarOption::MAX_EXTRACTED_SIZE ] ?? null  ;

    $sizeLimited = is_int( $maxExtractedSize ) && $maxExtractedSize >= 0 ;

    // Listing the archive is a pass of its own, so it is only made when one of the three
    // guarantees that need the contents in advance has been asked for. The common case —
    // extract this archive here — reads the archive once.

    $mustList = !$overwrite || $dryRun || $sizeLimited ;

    // Chosen here, before anything is written, and not revisited afterwards.

    $binary = untarBinaryHandles( $tarFile ) ? tarBinary() : null ;

    try
    {
        if ( $binary !== null )
        {
            $fileList = $mustList
                      ? untarAssertExtractable
                        (
                            untarEntries( $binary , $tarFile ) ,
                            $outputPath ,
                            $overwrite ,
                            $sizeLimited ? $maxExtractedSize : null
                        )
                      : [] ;

            if ( $dryRun )
            {
                return $fileList ;
            }

            untarWithBinary( $binary , $tarFile , $outputPath , $preservePermissions ) ;

            return true ;
        }

        $compression = tarIsCompressed( $tarFile ) ? tarCompressionOfFile( $tarFile ) : null ;

        // PharData decompresses through PHP extensions, and PHP has none for xz or zstd.
        // Said here, before anything is attempted, rather than left to come out of Phar as
        // a sentence about a format nobody mentioned: an archive the system tar wrote is
        // readable where there is a system tar, and this host has none.

        if ( $compression !== null && $compression !== CompressionType::GZIP && $compression !== CompressionType::BZIP2 )
        {
            throw new RuntimeException( sprintf
            (
                'this is a %1$s archive, and reading one needs the system GNU tar and its %1$s program — PharData reads gzip and bzip2 only. Install them, or point OIHANA_TAR_BINARY at a GNU tar.' ,
                $compression
            )) ;
        }

        $alias            = $compression === null ? null : untarUniqueAlias( $tarFile , $compression ) ;
        $decompressedPath = null ;

        // Decompressing through PharData means having it write a `.tar` named after the
        // archive, and register it in a list that removing the file does not clear. Working
        // from a uniquely named alias of the archive is what keeps a second extraction of
        // the same archive, in the same process, from colliding with the first.

        $phar = new PharData( $alias ?? $tarFile );

        if ( $compression !== null )
        {
            $phar = $phar->decompress() ;
            $decompressedPath = $phar->getPath();
        }

        assertTar( $phar->getPath() ) ;

        $fileList = [] ;

        if ( $mustList )
        {
            $entries      = [] ;
            $basePharPath = getPharBasePath( $phar ) ;

            foreach ( new RecursiveIteratorIterator( $phar ) as $file )
            {
                if ( !$file->isFile() )
                {
                    // The LEAVES_ONLY iterator over a PharData yields files only (no dir/symlink leaves).
                    // @codeCoverageIgnoreStart
                    continue;
                    // @codeCoverageIgnoreEnd
                }

                $entries[] =
                [
                    'name'      => str_replace( $basePharPath . '/' , '' , $file->getPathname() ) ,
                    'size'      => (int) $file->getSize() ,
                    'directory' => false ,
                ] ;
            }

            $fileList = untarAssertExtractable
            (
                $entries ,
                $outputPath ,
                $overwrite ,
                $sizeLimited ? $maxExtractedSize : null
            ) ;
        }

        if ( !$dryRun )
        {
            $phar->extractTo( $outputPath , null , $overwrite );

            if ( $preservePermissions )
            {
                preservePharFilePermissions( $phar , $outputPath );
            }
        }

        // Clean up what decompressing left behind, once, whichever way we got here.

        if ( $decompressedPath !== null && file_exists( $decompressedPath ) )
        {
            unlink( $decompressedPath ) ;
        }

        if ( $alias !== null && file_exists( $alias ) )
        {
            unlink( $alias ) ;
        }

        return $dryRun ? $fileList : true ;
    }
    catch ( Exception $exception )
    {
        $message = $exception->getMessage() ;

        // The one failure nobody could diagnose from its own words: an intact archive,
        // refused because PharData rebuilds it to decompress it and its writer enforces
        // the ustar limit. Read as "damaged archive", it sent people looking for a second
        // backup. Named, it is a five-minute fix on the host.

        if ( str_contains( $message , 'too long for tar file format' ) )
        {
            $message .= ' This is PharData refusing a name longer than the 100-byte ustar limit'
                      . ' while rebuilding the archive to decompress it, not a damaged archive:'
                      . ' GNU tar reads it without difficulty. Install one, or point'
                      . ' OIHANA_TAR_BINARY at it.' ;
        }

        throw new RuntimeException
        (
            sprintf
            (
                'Failed to extract tar file %s to directory %s. Error: %s',
                json_encode( $tarFile , JSON_UNESCAPED_SLASHES ),
                json_encode( $outputPath , JSON_UNESCAPED_SLASHES ),
                $message
            ) ,
            0 , $exception
        );
    }
}
