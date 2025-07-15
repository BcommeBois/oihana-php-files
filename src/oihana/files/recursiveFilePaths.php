<?php

namespace oihana\files ;

use FilesystemIterator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * Recursively retrieves all .php files in a folder (and its subfolders).
 * @param string $directory The base path of the file to be scanned.
 * @param array $options The optional parameter to send in the function.
 *  - excludes (array) : The enumeration of all files to excludes
 *  - extensions (array) : The optional list of the extensions to use to scan the folder(s).
 *  - maxDepth (int) : The maximum allowed depth. Default -1 is used
 *  - sortable (bool) : Indicates if the list of file paths is sorted before returned.
 * @return array The list of the full paths to all files found.
 */
function recursiveFilePaths( string $directory , array $options = [] ): array
{
    if ( !is_dir( $directory ) )
    {
        throw new RuntimeException( sprintf('The directory "%s" does not exist or is not a valid directory.', $directory ) );
    }

    $excludes   = $options[ 'excludes'   ] ?? [] ;
    $extensions = $options[ 'extensions' ] ?? null ;
    $maxDepth   = $options[ 'maxDepth'   ] ?? -1 ;
    $sortable   = $options[ 'sortable'   ] ?? true ;

    $extensions        = is_array( $extensions ) ? array_map('strtolower' , array_filter( $extensions ) ) : [] ;
    $filterByExtension = !empty( $extensions ) ;

    $files = [] ;

    try
    {
        $directoryIterator = new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ) ;

        $filterIterator = new RecursiveCallbackFilterIterator
        (
            $directoryIterator ,
            function( SplFileInfo $current ) use ( $excludes )
            {
                return !in_array( $current->getFilename() , $excludes , true ) ;
            }
        );

        $iterator = new RecursiveIteratorIterator( $filterIterator ) ;
        $iterator->setMaxDepth( $maxDepth ) ;

        /** @var SplFileInfo $file */
        foreach ( $iterator as $file )
        {
            if ( !$file->isFile() )
            {
                continue;
            }

            if ( $filterByExtension && !in_array( strtolower( $file->getExtension() ) , $extensions ) )
            {
                continue;
            }

            $files[] = $file->getPathname() ;
        }
    }
    catch ( RuntimeException $exception )
    {
        throw new RuntimeException( sprintf('Error during directory traversal: %s' , $exception->getMessage() ) , 0 , $exception ) ;
    }

    if( $sortable )
    {
        sort($files ) ;
    }

    return $files ;
}