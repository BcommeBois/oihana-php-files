<?php

namespace oihana\files ;

use FilesystemIterator ;
use RecursiveDirectoryIterator ;
use RecursiveIteratorIterator ;
use UnexpectedValueException ;

/**
 * Tells whether a directory holds a file, at any depth.
 *
 * Unlike {@see hasFiles()}, which only looks at the first level, this walks the whole tree:
 * a directory holding nothing but empty sub-directories holds no file, one holding a file five
 * levels down does.
 *
 * Links count as files and are not followed: a link is something the directory holds, whatever
 * it points at.
 *
 * **A directory that cannot be read is taken to hold a file**, and so is one with an unreadable
 * sub-directory. That is the safe answer for the usual question behind this call — "can this be
 * removed as empty?" — since what cannot be seen cannot be shown to be empty.
 *
 * @param string $directory The directory to look into.
 *
 * @return bool True when a file or a link is found anywhere under it, or when part of it cannot
 *              be read; false when it holds only directories, holds nothing, or is not there.
 *
 * @example
 * ```php
 * use function oihana\files\containsFile;
 *
 * // /tmp/work/a/b/ — empty directories only
 * containsFile( '/tmp/work' ) ; // false
 *
 * // /tmp/work/a/b/dump.sql
 * containsFile( '/tmp/work' ) ; // true
 * ```
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.7.0
 */
function containsFile( string $directory ) :bool
{
    if ( !is_dir( $directory ) )
    {
        return false ;
    }

    try
    {
        $entries = new RecursiveIteratorIterator
        (
            new RecursiveDirectoryIterator( $directory , FilesystemIterator::SKIP_DOTS ) ,
            RecursiveIteratorIterator::SELF_FIRST
        ) ;

        foreach ( $entries as $entry )
        {
            if ( $entry->isLink() || !$entry->isDir() )
            {
                return true ;
            }
        }
    }
    catch ( UnexpectedValueException )
    {
        return true ;
    }

    return false ;
}
