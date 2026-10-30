<?php

namespace oihana\files ;

use oihana\files\exceptions\DirectoryException ;

use function oihana\files\path\joinPaths ;

/**
 * Creates a working directory that only its owner can read: mode `0700`.
 *
 * Meant for the work that must not be seen by the other accounts of the machine while it is
 * in progress — a database dump, a configuration file with its credentials, the decrypted
 * content of an encrypted archive. With the usual `0755`, all of it is readable by every local
 * user for as long as it sits there.
 *
 * The mode is applied again after the creation, because a directory left behind by an earlier
 * run keeps the permissions it was created with, and the umask may have narrowed them anyway.
 *
 * **An empty `$path` is refused.** {@see makeTemporaryDirectory()} given `null` or `''` returns
 * the system temporary directory itself — it asks for a path, not a prefix — and a caller that
 * deletes what it was handed when the work is done would then delete the whole of `/tmp`.
 *
 * @param string|string[] $path The sub-path inside the base, as one string or as segments.
 *                              Empty segments are dropped.
 * @param string|null     $base The directory to create it in: the system temporary directory
 *                              when null, or another volume when the work is too large for it.
 *
 * @return string The path of the directory.
 *
 * @throws DirectoryException When the path is empty, or the directory cannot be created.
 *
 * @example
 * ```php
 * use function oihana\files\makePrivateTemporaryDirectory;
 * use function oihana\files\deleteDirectory;
 *
 * $work = makePrivateTemporaryDirectory( [ 'backup' , 'example.com' ] ) ;
 * // /tmp/backup/example.com, mode 0700
 *
 * $work = makePrivateTemporaryDirectory( 'backup' , '/srv/work' ) ;
 * // /srv/work/backup, mode 0700
 *
 * makePrivateTemporaryDirectory( '' ) ; // throws
 *
 * deleteDirectory( $work ) ;
 * ```
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.7.0
 */
function makePrivateTemporaryDirectory( string|array $path , ?string $base = null ) :string
{
    $segments = array_values
    (
        array_filter
        (
            array_map( 'strval' , is_array( $path ) ? $path : [ $path ] ) ,
            static fn( string $segment ) :bool => trim( $segment ) !== ''
        )
    ) ;

    if ( $segments === [] )
    {
        throw new DirectoryException
        (
            'A private temporary directory needs a name of its own: given none, it would be the '
            . 'base directory itself, and whoever deletes it afterwards would delete the base.'
        ) ;
    }

    $directory = $base === null
               ? makeTemporaryDirectory( $segments , 0700 )
               : (string) makeDirectory( joinPaths( $base , ...$segments ) , 0700 ) ;

    @chmod( $directory , 0700 ) ;

    return $directory ;
}
