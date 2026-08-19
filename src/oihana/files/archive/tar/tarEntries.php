<?php

namespace oihana\files\archive\tar;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Works out what an archive is to contain, and under which names.
 *
 * Written once and used by both engines, because the naming is the whole compatibility
 * contract: an archive is only interchangeable with the ones written before it if the entries
 * are called the same thing. Two copies of these rules would drift, and the drift would only
 * show up the day someone tried to restore an old backup.
 *
 * Each entry carries the directory it is named relative to, so the caller can either add it to
 * a `PharData` or hand the list to `tar -C <base>`.
 *
 * @param array<string> $paths        The real paths to archive, each proven to exist.
 * @param string|null   $preserveRoot The directory names are taken relative to, when given.
 *
 * @return array<int, array{base: string, name: string, directory: bool, path: string}>
 *         `base` is what `name` is relative to, `path` is where the content really is.
 *
 * @package oihana\files\archive\tar
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.3.0
 */
function tarEntries( array $paths , ?string $preserveRoot = null ) :array
{
    $preserveRootPath = $preserveRoot !== null ? realpath( $preserveRoot ) : null ;

    $entries = [] ;

    foreach ( $paths as $path )
    {
        $realPath = realpath( $path ) ;

        if ( $realPath === false )
        {
            continue ;
        }

        if ( !is_dir( $realPath ) )
        {
            // A lone file: relative to the preserved root when there is one, otherwise it
            // enters the archive under its own name and nothing else.

            $name = $preserveRootPath !== null
                  ? ltrim( str_replace( $preserveRootPath , '' , $realPath ) , DIRECTORY_SEPARATOR )
                  : basename( $realPath ) ;

            if ( $name !== '' )
            {
                $entries[] =
                [
                    'base'      => $preserveRootPath ?? dirname( $realPath ) ,
                    'name'      => $name ,
                    'directory' => false ,
                    'path'      => $realPath ,
                ] ;
            }

            continue ;
        }

        // A directory archived as itself keeps its contents at the top level; archived from
        // anywhere else it keeps its own name as a prefix, so that extracting it recreates
        // the directory rather than spilling its contents.

        $asRoot = $preserveRootPath === $realPath ;
        $base   = $asRoot ? $realPath : dirname( $realPath ) ;
        $prefix = $asRoot ? '' : basename( $realPath ) . DIRECTORY_SEPARATOR ;

        if ( !$asRoot )
        {
            $entries[] =
            [
                'base'      => $base ,
                'name'      => basename( $realPath ) ,
                'directory' => true ,
                'path'      => $realPath ,
            ] ;
        }

        $iterator = new RecursiveIteratorIterator
        (
            new RecursiveDirectoryIterator( $realPath , FilesystemIterator::SKIP_DOTS ) ,
            RecursiveIteratorIterator::SELF_FIRST
        ) ;

        // The sub-path is cut from the item rather than asked of the iterator.
        //
        // `RecursiveIteratorIterator::getSubPathName()` is not declared on that class: it
        // works because unknown calls are forwarded to the inner iterator, which does declare
        // it. Valid at runtime, invisible to static analysis, and one refactor away from
        // silently returning something else. Every item here lives under $realPath, so the
        // name is simply what follows it.

        $offset = strlen( $realPath ) + 1 ;

        foreach ( $iterator as $item )
        {
            $entries[] =
            [
                'base'      => $base ,
                'name'      => $prefix . substr( $item->getPathname() , $offset ) ,
                'directory' => $item->isDir() ,
                'path'      => $item->getPathname() ,
            ] ;
        }
    }

    return $entries ;
}

/**
 * Groups entries by the directory their names are relative to.
 *
 * `tar` is told a base with `-C` and reads the names from standard input, so one invocation
 * covers one base. Archiving several unrelated paths at once therefore takes several.
 *
 * @param array<int, array{base: string, name: string, directory: bool, path: string}> $entries
 *
 * @return array<string, array<int, string>> The names to archive, keyed by base directory.
 *
 * @package oihana\files\archive\tar
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.3.0
 */
function tarEntriesByBase( array $entries ) :array
{
    $grouped = [] ;

    foreach ( $entries as $entry )
    {
        $grouped[ $entry[ 'base' ] ][] = $entry[ 'name' ] ;
    }

    return $grouped ;
}
