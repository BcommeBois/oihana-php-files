<?php

namespace oihana\files ;

use DirectoryIterator;
use FilesystemIterator;
use oihana\files\enums\FindMode;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo ;

use oihana\enums\Char;
use oihana\files\exceptions\DirectoryException;
use function oihana\core\strings\isRegexp;

/**
 * List files in a directory (non-recursive by default), with rich filtering, sorting, and recursive options.
 * @param ?string $directory The directory path.
 * @param array{
 *     filter      : null|callable ,
 *     followLinks : null|bool ,
 *     includeDots : null|bool ,
 *     mode        : null|string ,
 *     order       : null|string  ,
 *     pattern     : null|array|string ,
 *     recursive   : null|bool ,
 *     sort        : null|callable|string
*   } $options
 *  <li><b>filter</b> : The optional function to filter all files, ex: <code>fn( $file ) => $file->getFileName()</code></li>
 *  <li><b>followLinks</b> : Indicates whether symbolic links should be followed (default false).</li>
 *  <li><b>includeDots</b> : Indicates if the dot files are included (default false).</li>
 *  <li><b>mode</b> : Filter mode, possible values:
 *      <ul>
 *          <li>'files' (default) : list files only.</li>
 *          <li>'dirs' (default) : list directories only.</li>
 *          <li>'files' (default) : list files and directories.</li>
  *      </ul>
 *  </li>
 *  <li><b>order</b> : The order of the file sorting : default 'asc' or 'desc'.
 *  <li><b>pattern</b> : A pattern (a regexp, a glob, or a string) or an array of patterns.</li>
 *  <li><b>recursive</b> : Indicates if all sub-directories are browsed (default false).</li>
 *  <li><b>sort</b> : The optional sort option to sort all files, ex: <code>fn( SplFileInfo $a, SplFileInfo $b ) => return strcmp($a->getRealPath(), $b->getRealPath())</code></li>
 * @return SplFileInfo[]
 * @throws DirectoryException
 * @examples
 * ```php
 *  // 1) Finder‑like->sortByName()
 *  $files = findFiles('/var/www', ['sort' => 'name']);
 *
 *  // 2) Case‑insensitive name, descending
 *  $files = findFiles('/var/www', ['sort' => 'ci_name', 'order' => 'desc']);
 *
 *  // 3) Directories first, then alphabetical (type + name)
 *  $files = findFiles('/var/www', ['sort' => ['type', 'name']]);
 *
 *  // 4) Size descending with custom comparator
 *  $files = findFiles('/var/www', [
 *      'sort'  => fn(SplFileInfo $a, SplFileInfo $b) => $a->getSize() <=> $b->getSize(),
 *      'order' => 'desc',
 *  ]);
 *
 *  // 5) Recursive search for *.log and *.txt, ignore dot‑files, follow symlinks
 *  $files = findFiles('/var/log', [
 *      'pattern'    => ['*.log', '*.txt'],
 *      'recursive'   => true,
 *      'followLinks' => true,
 *  ]);
 *
 *  // 6) Include dot‑files and map to filenames only
 *  $names = findFiles('/tmp', [
 *      'includeDots' => true,
 *      'filter'    => fn(SplFileInfo $f) => $f->getFilename(),
 *  ]);
 *
 *  // 7) Mixed glob + regexp filter
 *  $files = findFiles('/data', [
 *      'pattern' => ['*.csv', '/^report_\d{4}\.xlsx$/i'],
 *  ]);
 *  ```
 * </code>
 * @see sortFiles()
 * @see FindFindOption
 * @see FindMode
 * @see Order::asc
 */
function findFiles( ?string $directory, array $options = [] ): array
{
    assertDirectory( $directory );

    $filter      = $options[ 'filter'      ] ?? null  ;
    $followLinks = $options[ 'followLinks' ] ?? false ;
    $includeDots = $options[ 'includeDots' ] ?? false ;
    $order       = $options[ 'order'       ] ?? 'asc' ;
    $mode        = $options[ 'mode'        ] ?? FindMode::FILES ;
    $patterns    = $options[ 'pattern'     ] ?? null  ;
    $recursive   = $options[ 'recursive'   ] ?? false ;
    $sort        = $options[ 'sort'        ] ?? false ;

    $files = [];

    $iterator = $recursive
              ? new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $directory , ( $followLinks ? FilesystemIterator::FOLLOW_SYMLINKS : 0 ) | FilesystemIterator::SKIP_DOTS ) )
              : new DirectoryIterator( $directory ) ;

    foreach ( $iterator as $file )
    {
        switch ( $mode )
        {
            case FindMode::FILES :
            {
                if ( !$file->isFile() )
                {
                    continue 2 ;
                }
                break;
            }

            case FindMode::DIRS :
            {
                if ( !$file->isDir() )
                {
                    continue 2 ;
                }
                break ;
            }

            case FindMode::BOTH :
            {
                break ;
            }

            default:
            {
                throw new DirectoryException("Invalid option 'mode' value: {$mode}" ) ;
            }
        }

        $fileName = $file->getFilename();

        if ( !$includeDots && str_starts_with( $fileName, Char::DOT ) )
        {
            continue;
        }

        $match = true ;

        if ( !empty( $patterns ) )
        {
            foreach ( (array) $patterns as $pattern )
            {
                $match = isRegexp( $pattern )
                       ? (bool) preg_match($pattern, $fileName)
                       : fnmatch( $pattern , $fileName ) ;

                if ( $match ) break ;
            }

            if ( !$match )
            {
                continue;
            }
        }

        $files[] = new SplFileInfo( $file->getPathname() ) ;
    }

    if ( $sort )
    {
        sortFiles( $files , $sort , $order ) ;
    }

    if ( !empty($files) && is_callable( $filter ) )
    {
        $files = array_map( $filter , $files ) ;
    }

    return $files;
}