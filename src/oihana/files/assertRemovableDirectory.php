<?php

namespace oihana\files ;

use oihana\files\exceptions\DirectoryException ;

/**
 * Asserts that a path is safe to hand to a recursive deletion.
 *
 * A path read from a configuration file ends up, sooner or later, in {@see deleteDirectory()} or
 * an `rm -rf`. A truncated, relative or mistyped value there turns a routine clean-up into the
 * loss of a system directory, so the path is checked as text before anything acts on it.
 *
 * A path is accepted when it is:
 *
 * - **not empty** ;
 * - **absolute** — a relative path depends on the current directory, which nobody checks ;
 * - **free of `.` and `..` segments** — `/var/www/site/..` is `/var/www` ;
 * - **deep enough** — at least `$minDepth` segments, so `/`, `/var` and `/var/www` are refused
 *   with the default of 3.
 *
 * Nothing is read on disk: the directory need not exist, and a link is judged by its own path.
 * This is a guard against a wrong value, not against a hostile one.
 *
 * @param string $path     The directory about to be deleted.
 * @param int    $minDepth The number of segments the path must have, at least.
 *
 * @return void
 *
 * @throws DirectoryException When the path is not safe to delete.
 *
 * @example
 * ```php
 * use function oihana\files\assertRemovableDirectory;
 * use function oihana\files\deleteDirectory;
 *
 * assertRemovableDirectory( '/var/www/example.com/htdocs' ) ; // passes
 * assertRemovableDirectory( '/var/www' ) ;                    // throws: 2 segments, 3 required
 * assertRemovableDirectory( 'www/example.com' ) ;             // throws: relative
 * assertRemovableDirectory( '/var/www/site/..' ) ;            // throws: relative segment
 *
 * assertRemovableDirectory( $path ) ;
 * deleteDirectory( $path ) ;
 * ```
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.7.0
 */
function assertRemovableDirectory( string $path , int $minDepth = 3 ) :void
{
    $trimmed = trim( $path ) ;

    if ( $trimmed === '' )
    {
        throw new DirectoryException( 'Refusing to delete an empty directory path.' ) ;
    }

    if ( !str_starts_with( $trimmed , '/' ) )
    {
        throw new DirectoryException( sprintf( 'Refusing to delete a relative directory path: "%s".' , $trimmed ) ) ;
    }

    $segments = array_values( array_filter( explode( '/' , $trimmed ) , static fn( string $segment ) :bool => $segment !== '' ) ) ;

    foreach ( $segments as $segment )
    {
        if ( $segment === '.' || $segment === '..' )
        {
            throw new DirectoryException( sprintf( 'Refusing to delete a directory path with relative segments: "%s".' , $trimmed ) ) ;
        }
    }

    if ( count( $segments ) < $minDepth )
    {
        throw new DirectoryException
        (
            sprintf
            (
                'Refusing to delete "%s": the path is too close to the filesystem root (%d segment(s), %d required).' ,
                $trimmed ,
                count( $segments ) ,
                $minDepth
            )
        ) ;
    }
}
