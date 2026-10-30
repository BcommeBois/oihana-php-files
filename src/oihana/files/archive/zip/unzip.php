<?php

namespace oihana\files\archive\zip;

use ZipArchive;

use oihana\files\enums\ZipOption;
use oihana\files\exceptions\DirectoryException;
use oihana\files\exceptions\FileException;

use function oihana\files\makeDirectory;
use function oihana\files\path\canonicalizePath;
use function oihana\files\path\isAbsolutePath;
use function oihana\files\path\isBasePath;
use function oihana\files\path\joinPaths;

/**
 * Extracts a zip archive into a destination directory.
 *
 * This function mirrors {@see \oihana\files\archive\tar\untar()}. It guards against path
 * traversal (Zip Slip) and decompression bombs, can preview the contents without writing
 * anything (dry run), and can refuse to overwrite existing files.
 *
 * **Symbolic links are restored as links.** An entry whose stored Unix mode carries the link type
 * bits — what {@see zip()} writes for every link it meets — is recreated with `symlink()` and its
 * content read as the target. Such a target is refused when it is absolute or when it escapes the
 * destination directory, since a link is the one entry whose content is itself a path and the Zip
 * Slip guard on entry names does not cover it. The `allowUnsafeLinks` option lifts that refusal for
 * an archive the caller answers for — a backup of a tree whose links were absolute on the disk it
 * was taken from, which `zip()` stores faithfully and which could otherwise not be restored.
 *
 * @param string $zipFile    Path to the zip archive to extract.
 * @param string $outputPath Directory where the archive is extracted. Created if missing.
 * @param array{dryRun?: bool, overwrite?: bool, maxEntries?: int|null, maxSize?: int|null, keepPermissions?: bool, allowUnsafeLinks?: bool} $options Optional flags, keyed by {@see ZipOption}:
 *   - **dryRun**: If true, no file is written; returns the list of file entries that would be
 *     extracted (directory entries excluded). Default: false.
 *   - **overwrite**: If false, extraction fails when a target file already exists. Default: true.
 *   - **maxEntries**: If a positive integer, the archive is rejected when it declares more
 *     entries than this limit (decompression-bomb guard). Default: null (no limit).
 *   - **maxSize**: If a positive integer, the archive is pre-scanned and rejected **before**
 *     any file is written when the sum of the entries' uncompressed sizes exceeds this limit
 *     (decompression-bomb guard). Default: null (no limit).
 *   - **allowUnsafeLinks**: If true, recreates a symbolic link whose target is absolute or escapes
 *     the destination directory, which is refused by default. Only for an archive the caller
 *     answers for: once such a link stands in the destination, the entries written after it can
 *     reach through it, outside. Default: false.
 *   - **keepPermissions**: If true, restores the Unix file mode stored in each entry's external
 *     attributes (`OPSYS_UNIX`) via `chmod()`. Entries without Unix permissions are left with the
 *     default mode, and a symbolic link is left alone, since `chmod()` would follow it and change
 *     the permissions of its target. Best-effort: a failing `chmod()` is ignored. Default: false.
 *
 * @return true|string[] Returns true on successful extraction, or the list of file entries
 *                       (relative to the archive root) when dryRun is enabled.
 *
 * @throws FileException If the archive does not exist, cannot be opened, an entry escapes the
 *                       destination (Zip Slip), an entry is a symbolic link whose target is
 *                       absolute or escapes the destination while `allowUnsafeLinks` is off, an
 *                       entry is a symbolic link without a target, a link cannot be created, a bomb
 *                       guard trips, a target already exists while overwrite is disabled, an
 *                       entry cannot be read or does not match its recorded checksum, or an
 *                       extracted file cannot be written.
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
        $dryRun          = $options[ ZipOption::DRY_RUN          ] ?? false ;
        $overwrite       = $options[ ZipOption::OVERWRITE        ] ?? true  ;
        $maxEntries      = $options[ ZipOption::MAX_ENTRIES      ] ?? null  ;
        $maxSize         = $options[ ZipOption::MAX_SIZE         ] ?? null  ;
        $keepPermissions = $options[ ZipOption::KEEP_PERMISSIONS ] ?? false ;
        $unsafeLinks     = $options[ ZipOption::ALLOW_UNSAFE_LINKS ] ?? false ;

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

            // The Unix mode an entry carries says what the entry is, not only how it may be read:
            // the type bits tell a symbolic link from a regular file, and zip() writes them for
            // every link it stores.

            $mode  = 0 ;
            $opSys = 0 ;
            $attr  = 0 ;

            if ( $zip->getExternalAttributesIndex( $i , $opSys , $attr ) && $opSys === ZipArchive::OPSYS_UNIX )
            {
                $mode = $attr >> 16 ;
            }

            $isLink = !$isDir && ( $mode & 0o170000 ) === 0o120000 ;

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
            }
            else if ( $isLink )
            {
                $linkTarget = (string) $zip->getFromIndex( $i ) ;

                // A link is the one entry whose content is itself a path, so the Zip Slip guard
                // above, which reads entry names, does not cover it: a link to "/etc" or to
                // "../.." hands every entry written afterwards a way out of the destination.

                if ( $linkTarget === '' )
                {
                    throw new FileException( sprintf('The entry "%s" is a symbolic link without a target.' , $name ) ) ;
                }

                // A target that leaves the destination is refused unless the caller answers for
                // the archive. A link without a target is refused either way: it is malformed,
                // not a matter of trust.

                if ( !$unsafeLinks )
                {
                    if ( isAbsolutePath( $linkTarget ) )
                    {
                        throw new FileException( sprintf('Zip Slip detected: the entry "%s" is a symbolic link to the absolute path "%s". Set the "%s" option to extract it anyway.' , $name , $linkTarget , ZipOption::ALLOW_UNSAFE_LINKS ) ) ;
                    }

                    if ( !isBasePath( $base , canonicalizePath( joinPaths( dirname( $target ) , $linkTarget ) ) ) )
                    {
                        throw new FileException( sprintf('Zip Slip detected: the entry "%s" is a symbolic link to "%s", which escapes the destination directory. Set the "%s" option to extract it anyway.' , $name , $linkTarget , ZipOption::ALLOW_UNSAFE_LINKS ) ) ;
                    }
                }

                if ( !$overwrite && ( is_link( $target ) || file_exists( $target ) ) )
                {
                    throw new FileException( sprintf('The target file "%s" already exists.' , $target ) ) ;
                }

                makeDirectory( dirname( $target ) ) ;

                // symlink() writes over nothing, so whatever stands there goes first. It is
                // unlinked, never followed, so a link already in place keeps its own target.

                if ( is_link( $target ) || file_exists( $target ) )
                {
                    @unlink( $target ) ;
                }

                if ( !@symlink( $linkTarget , $target ) )
                {
                    throw new FileException( sprintf('Failed to create the symbolic link "%s" to "%s".' , $target , $linkTarget ) ) ;
                }

                // chmod() follows a link to whatever it points at, so the mode stored with a link
                // is not applied: it would change the permissions of its target instead.

                continue ;
            }
            else
            {
                if ( !$overwrite && file_exists( $target ) )
                {
                    throw new FileException( sprintf('The target file "%s" already exists.' , $target ) ) ;
                }

                makeDirectory( dirname( $target ) ) ;

                // Both halves were unchecked, and each has its own silence: an entry that cannot
                // be read answers false, which written out gives an empty file, and a write that
                // fails — a full disk, a target that is a directory — left the extraction
                // reporting success for a file it did not write.

                $contents = $zip->getFromIndex( $i ) ;

                if ( $contents === false )
                {
                    throw new FileException( sprintf('Cannot read the entry "%s" of the zip archive.' , $name ) ) ;
                }

                // libzip does not check an entry against its CRC when it reads it. A damaged
                // deflated stream comes back as whatever inflated before the damage — 2 bytes of
                // 840 when it was measured — and an altered stored entry comes back altered, with
                // no error either way. The catalogue records the CRC each entry was written with,
                // so the content is held to it before it is written anywhere.

                if ( crc32( $contents ) !== (int) $zip->statIndex( $i )[ 'crc' ] )
                {
                    throw new FileException( sprintf
                    (
                        'The entry "%s" of the zip archive is corrupt: its content does not match the checksum the archive records for it.' ,
                        $name
                    )) ;
                }

                // @-suppressed: file_put_contents() warns on a failure the exception below reports.

                if ( @file_put_contents( $target , $contents ) === false )
                {
                    throw new FileException( sprintf('Cannot write the extracted file "%s".' , $target ) ) ;
                }
            }

            if ( $keepPermissions && ( $mode & 0o7777 ) !== 0 )
            {
                // @-suppressed: chmod() warns when the caller does not own the target; best-effort only.
                @chmod( $target , $mode & 0o7777 ) ;
            }
        }

        return $dryRun ? $entries : true ;
    }
    finally
    {
        $zip->close() ;
    }
}
