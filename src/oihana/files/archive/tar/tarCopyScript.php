<?php

namespace oihana\files\archive\tar ;

/**
 * Builds the bash script that copies part of a directory into another through two GNU tar.
 *
 * `tar -cf - | tar -xf -` rather than `cp`, because tar never reads what it excludes: a cache or
 * an archive left in the tree costs nothing to leave out. What this script adds is the reading of
 * the two exit codes, which `set -o pipefail` would merge into one:
 *
 * - the tar that **reads** exits 0, **1 when files moved while it read them** — modified, removed
 *   before being read, or shrunk — and 2 for a fatal error. On a directory that is in use, 1
 *   happens by chance: the same copy passes a minute later. So the script exits with that code,
 *   for {@see tarCopyOutcome()} to judge from what tar said;
 * - the tar that **writes** must end in 0, always: a full disk shows there. When it does not, the
 *   script exits 3, a code the reading tar never uses for this.
 *
 * It runs under `LC_ALL=C`: a tar that speaks the language of the machine reports a moved file in
 * that language, and {@see tarCopyOutcome()} reads the English messages of GNU tar. The script
 * runs `tar` as the PATH finds it, and relies on bash for `PIPESTATUS`: run it with `bash -c`.
 *
 * Every path and pattern is escaped for the shell.
 *
 * @param string        $source      The directory the members are read from.
 * @param array<string> $members     What is copied, relative to `$source`.
 * @param string        $destination The directory the copy is written to. It must exist.
 * @param array<string> $exclude     `--exclude` patterns, as GNU tar reads them
 *                                   ({@see tarExcludesPath()}).
 *
 * @return string The script, for `bash -c`.
 *
 * @example
 * ```php
 * use function oihana\files\archive\tar\tarCopyOutcome;
 * use function oihana\files\archive\tar\tarCopyScript;
 *
 * $script = tarCopyScript( '/srv/app' , [ 'content' ] , '/tmp/copy' , [ 'content/cache' ] ) ;
 *
 * $process = proc_open( [ 'bash' , '-c' , $script ] , [ 2 => [ 'pipe' , 'w' ] ] , $pipes ) ;
 * $error   = stream_get_contents( $pipes[ 2 ] ) ;
 * fclose( $pipes[ 2 ] ) ;
 *
 * $outcome = tarCopyOutcome( proc_close( $process ) , $error ) ;
 * ```
 *
 * @package oihana\files\archive\tar
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.7.0
 */
function tarCopyScript( string $source , array $members , string $destination , array $exclude = [] ) :string
{
    $patterns = implode( '' , array_map( static fn( string $pattern ) :string => ' --exclude=' . escapeshellarg( $pattern ) , $exclude ) ) ;

    return sprintf
    (
        'export LC_ALL=C ; tar -C %s%s -cf - -- %s | tar -C %s -xf - ; '
        . 'status=( "${PIPESTATUS[@]}" ) ; [ "${status[1]}" -eq 0 ] || exit 3 ; exit "${status[0]}"' ,
        escapeshellarg( $source ) ,
        $patterns ,
        implode( ' ' , array_map( 'escapeshellarg' , $members ) ) ,
        escapeshellarg( $destination )
    ) ;
}
