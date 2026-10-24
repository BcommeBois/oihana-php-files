<?php

namespace oihana\files\archive\tar ;

use RuntimeException;

/**
 * Extracts an archive with the system `tar`.
 *
 * **Why this exists, and it is not about speed.** PharData extracts a plain `.tar` at a
 * perfectly good pace — measured on 2026-10-24, 0.98 s against 0.82 s for GNU tar on the
 * same 57 MB archive. It is the compressed case that breaks, and it breaks in three ways
 * at once, all from one line: `PharData::decompress()` has PharData **write** a new
 * `.tar` before reading it.
 *
 * 1. **It cannot read what this library writes.** PharData's writer refuses any path
 *    *component* over 100 bytes — the `ustar` limit — so a `.tar.gz` holding one long
 *    name fails to extract with *"filename ... is too long for tar file format"*, while
 *    the same archive uncompressed extracts fine. Since 1.3.0 `tar()` delegates to GNU
 *    tar, which stores those names without difficulty: the two halves of this library
 *    had stopped agreeing, and a stock WordPress has such a path.
 * 2. **Memory.** The rebuild costs about twice the archive — measured at ×2.26 on 60 MB
 *    and ×2.04 on 200 MB, so around 5 GB for a 2.45 GB backup.
 * 3. **Disk.** The intermediate `.tar` is written beside the archive, uncompressed, for
 *    the length of the extraction.
 *
 * `tar` streams instead, and knows nothing of the 100-byte limit.
 *
 * Two flags carry decisions:
 *
 * - **`--no-same-owner`, always.** As root — which is how a restore runs — GNU tar
 *   restores the owner recorded in the archive, while PharData never could. Keeping it
 *   off is what makes the two engines produce the same tree, and stops an archive from
 *   deciding who owns the files it is unpacked into.
 * - **`-p` only when asked**, matching `keepPermissions`. Without it `tar` applies the
 *   umask, as PharData does.
 *
 * Path traversal needs no flag: GNU tar strips a leading `/` and refuses any member
 * whose name contains `..`, extracting nothing and exiting non-zero — which this reports.
 *
 * @param string $binary              The GNU tar to run, from {@see tarBinary()}.
 * @param string $tarFile             Path to the archive.
 * @param string $outputPath          Existing directory to extract into.
 * @param bool   $preservePermissions Whether to restore the recorded permissions.
 *
 * @throws RuntimeException If `tar` exits non-zero.
 *
 * @example
 * ```php
 * use function oihana\files\archive\tar\tarBinary;
 * use function oihana\files\archive\tar\untarWithBinary;
 *
 * untarWithBinary( tarBinary() , '/backups/site.tar.gz' , '/var/www/staging' ) ;
 * ```
 *
 * @package oihana\files\archive\tar
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.5.0
 */
function untarWithBinary( string $binary , string $tarFile , string $outputPath , bool $preservePermissions = false ) :void
{
    $command = [ $binary , '-C' , $outputPath , '-xf' , $tarFile , '--no-same-owner' ] ;

    if ( $preservePermissions )
    {
        $command[] = '-p' ;
    }

    $descriptors = [ 1 => [ 'pipe' , 'w' ] , 2 => [ 'pipe' , 'w' ] ] ;

    $process = @proc_open( $command , $descriptors , $pipes , null , [ 'LC_ALL' => 'C' ] ) ;

    // Reachable only if the process table is exhausted; tarBinary() has already run this
    // very binary to identify it.
    // @codeCoverageIgnoreStart
    if ( !is_resource( $process ) )
    {
        throw new RuntimeException( sprintf( 'untarWithBinary() failed, could not run %s.' , $binary ) ) ;
    }
    // @codeCoverageIgnoreEnd

    $errors = stream_get_contents( $pipes[ 2 ] ) ?: '' ;

    fclose( $pipes[ 1 ] ) ;
    fclose( $pipes[ 2 ] ) ;

    $status = proc_close( $process ) ;

    if ( $status !== 0 )
    {
        throw new RuntimeException( sprintf
        (
            'untarWithBinary() failed, %s exited with status %d while extracting %s: %s' ,
            $binary ,
            $status ,
            $tarFile ,
            trim( $errors ) === '' ? 'no error reported' : trim( $errors )
        )) ;
    }
}
