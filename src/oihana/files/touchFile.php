<?php

namespace oihana\files ;

use oihana\files\exceptions\DirectoryException;
use oihana\files\exceptions\FileException;

/**
 * Creates a file if it does not exist, or updates its modification and access times.
 *
 * Thin, exception-throwing wrapper around `touch()`. When `$mtime` is `null`, the
 * current time is used. When `$mtime` is given but `$atime` is `null`, the access
 * time is set to `$mtime` as well. The parent directory is created on demand.
 *
 * @param string   $file            Path to the file to touch.
 * @param int|null $mtime           Modification timestamp (default: `null` → current time).
 * @param int|null $atime           Access timestamp (default: `null` → same as `$mtime`).
 * @param bool     $createDirectory Whether to create the parent directory if missing (default: `true`).
 *
 * @return string The file path.
 *
 * @throws FileException      If the file cannot be touched.
 * @throws DirectoryException If the parent directory is missing and cannot be created.
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.2.0
 *
 * @example
 * ```php
 * use function oihana\files\touchFile;
 *
 * touchFile( '/var/run/app.lock' ) ;                 // create or bump to now
 * touchFile( '/data/marker' , strtotime('-1 day') ) ; // backdate the mtime
 * ```
 */
function touchFile( string $file , ?int $mtime = null , ?int $atime = null , bool $createDirectory = true ): string
{
    $directory = dirname( $file ) ;

    if ( $createDirectory && !is_dir( $directory ) )
    {
        makeDirectory( $directory ) ;
    }

    $result = $mtime === null
        ? @touch( $file )
        : @touch( $file , $mtime , $atime ?? $mtime ) ;

    if ( !$result )
    {
        throw new FileException( sprintf( 'Failed to touch the file "%s".' , $file ) ) ;
    }

    return $file ;
}
