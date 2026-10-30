<?php

namespace oihana\files ;

use FilesystemIterator ;
use RecursiveDirectoryIterator ;
use RecursiveIteratorIterator ;
use UnexpectedValueException ;

/**
 * Lists every path under a directory — files, directories and links — relative to a root.
 *
 * Unlike {@see recursiveFilePaths()}, which returns the files only, this lists the directories
 * too, so two trees can be compared entry by entry: what one holds and the other does not.
 *
 * Symbolic links are listed and **never followed**. A link pointing at `/` would otherwise list
 * the whole machine, and a caller removing "what the other tree does not have" would be handed
 * paths outside the directory it was asked about. For the same reason, a `$directory` that is
 * itself a link gives an empty list.
 *
 * A sub-directory that cannot be read is listed, and its content is skipped; a `$directory`
 * that cannot be read gives an empty list. Paths always use `/`, whatever the system.
 *
 * @param string $directory The directory to walk.
 * @param string $root      What the paths are made relative to — the directory itself when
 *                          empty. Give a parent to keep the directory's own name in each path.
 *
 * @return list<string> The paths, in the order the filesystem returns them, each directory
 *                      before its content. Empty when the directory is not there.
 *
 * @example
 * ```php
 * use function oihana\files\treePaths;
 *
 * // /srv/site/content/a.txt and /srv/site/content/img/b.png
 *
 * treePaths( '/srv/site/content' ) ;
 * // [ 'a.txt' , 'img' , 'img/b.png' ]
 *
 * treePaths( '/srv/site/content' , '/srv/site' ) ;
 * // [ 'content/a.txt' , 'content/img' , 'content/img/b.png' ]
 * ```
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.7.0
 */
function treePaths( string $directory , string $root = '' ) :array
{
    $directory = rtrim( $directory , '/' ) ;
    $root      = rtrim( $root === '' ? $directory : $root , '/' ) ;

    if ( !is_dir( $directory ) || is_link( $directory ) )
    {
        return [] ;
    }

    $paths = [] ;

    try
    {
        $iterator = new RecursiveIteratorIterator
        (
            new RecursiveDirectoryIterator( $directory , FilesystemIterator::SKIP_DOTS ) ,
            RecursiveIteratorIterator::SELF_FIRST ,
            RecursiveIteratorIterator::CATCH_GET_CHILD
        ) ;

        foreach ( $iterator as $file )
        {
            $paths[] = ltrim( str_replace( '\\' , '/' , substr( (string) $file->getPathname() , strlen( $root ) ) ) , '/' ) ;
        }
    }
    catch ( UnexpectedValueException )
    {
        // The directory itself cannot be read.
    }

    return $paths ;
}
