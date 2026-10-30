<?php

namespace oihana\files\archive\tar ;

use RuntimeException;

use oihana\enums\Char;
use oihana\files\enums\TarOption;

use function oihana\files\path\canonicalizePath;
use function oihana\files\path\isAbsolutePath;
use function oihana\files\path\isBasePath;
use function oihana\files\path\joinPaths;

/**
 * Checks what an archive holds against what the caller allows, before anything is written.
 *
 * The three guarantees of {@see untar()} that cannot be made while extracting — a
 * refusal to overwrite, the decompression-bomb cap, and the dry run that reports and
 * does nothing — all come down to the same question asked of a listing. Both engines
 * produce that listing, so the question is asked in one place: a cap enforced one way
 * for the system `tar` and another for PharData would be two caps, and one of them
 * would be wrong.
 *
 * All of it happens **before** the first byte is extracted. A cap that fires half way
 * leaves a directory the caller has to clean up, and a refusal to overwrite that fires
 * on the fourth file has already overwritten three.
 *
 * @param array<int, array{name: string, size: int, directory: bool}> $entries What the
 *        archive holds, from {@see untarEntries()} or read off a PharData iterator.
 * @param string   $outputPath       Where the archive would be extracted.
 * @param bool     $overwrite        When false, an existing target is refused.
 * @param int|null $maxExtractedSize Cap on the total uncompressed size; `null` for none.
 *
 * @return array<int, string> The names of the files the archive holds, directories left
 *         out — the list a dry run returns.
 *
 * @throws RuntimeException If a name escapes the output directory, a target exists while
 *                          `$overwrite` is false, or the total exceeds the cap.
 *
 * @example
 * ```php
 * use function oihana\files\archive\tar\{tarBinary, untarAssertExtractable, untarEntries};
 *
 * $files = untarAssertExtractable
 * (
 *     untarEntries( tarBinary() , '/backups/site.tar.gz' ) ,
 *     '/var/www/staging' ,
 *     false ,
 *     2 * 1024 * 1024 * 1024
 * ) ;
 * ```
 *
 * @package oihana\files\archive\tar
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.5.0
 */
function untarAssertExtractable( array $entries , string $outputPath , bool $overwrite = true , ?int $maxExtractedSize = null , bool $allowUnsafeLinks = false ) :array
{
    $files     = [] ;
    $totalSize = 0 ;
    $base      = canonicalizePath( $outputPath ) ;

    foreach ( $entries as $entry )
    {
        $name = $entry[ 'name' ] ;

        if ( str_contains( $name , Char::DOUBLE_DOT ) )
        {
            throw new RuntimeException( sprintf( 'Path traversal attempt detected in tar file: %s' , $name ) ) ;
        }

        // A link is the one entry whose content is itself a path, and the check above reads names.
        // A link to "/etc" or to "../.." hands every entry written after it a way out of the
        // output directory, since a name like "link/passwd" is inside it and the file is not.

        $target = $entry[ 'target' ] ?? null ;

        if ( $target !== null && !$allowUnsafeLinks )
        {
            if ( isAbsolutePath( $target ) )
            {
                throw new RuntimeException( sprintf
                (
                    'Path traversal attempt detected in tar file: the entry "%s" is a link to the absolute path "%s". Set the "%s" option to extract it anyway.' ,
                    $name ,
                    $target ,
                    TarOption::ALLOW_UNSAFE_LINKS
                )) ;
            }

            if ( !isBasePath( $base , canonicalizePath( joinPaths( dirname( joinPaths( $outputPath , $name ) ) , $target ) ) ) )
            {
                throw new RuntimeException( sprintf
                (
                    'Path traversal attempt detected in tar file: the entry "%s" is a link to "%s", which escapes the output directory. Set the "%s" option to extract it anyway.' ,
                    $name ,
                    $target ,
                    TarOption::ALLOW_UNSAFE_LINKS
                )) ;
            }
        }

        if ( $entry[ 'directory' ] )
        {
            continue ;
        }

        $files[] = $name ;

        if ( !$overwrite )
        {
            $targetPath = $outputPath . DIRECTORY_SEPARATOR . $name ;

            if ( file_exists( $targetPath ) )
            {
                throw new RuntimeException( sprintf( 'untar() failed, the file %s already exists and overwrite is disabled.' , $targetPath ) ) ;
            }
        }

        if ( $maxExtractedSize !== null )
        {
            $totalSize += $entry[ 'size' ] ;

            if ( $totalSize > $maxExtractedSize )
            {
                throw new RuntimeException( sprintf
                (
                    'untar() aborted: extracted size exceeds maximum %d bytes (potential decompression bomb).' ,
                    $maxExtractedSize
                )) ;
            }
        }
    }

    return $files ;
}
