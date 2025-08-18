<?php

namespace oihana\files ;

use DirectoryIterator;
use FilesystemIterator;
use oihana\enums\Order;
use oihana\files\enums\FindFilesOption;
use oihana\files\enums\FindMode;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo ;

use oihana\enums\Char;
use oihana\files\exceptions\DirectoryException;
use function oihana\core\strings\isRegexp;

/**
 * Lists files in a directory with advanced filtering, sorting, and recursive options.
 *
 * This function provides flexible options for retrieving files and directories from a given path.
 * It supports recursive search, glob and regex pattern matching, sorting, symbolic link following, and custom filters.
 *
 * @param ?string $directory The target directory path. If null or invalid, a DirectoryException is thrown.
 * @param array{
 *    filter?      : (callable(SplFileInfo): mixed)|null,
 *    followLinks? : bool|null,
 *    includeDots? : bool|null,
 *    mode?        : string|null,
 *    order?       : string|null,
 *    pattern?     : string|array|null,
 *    recursive?   : bool|null,
 *    sort?        : callable|string|array|null
 * } $options Optional settings to customize the file listing.
 *
 * - filter : A function to map or transform each SplFileInfo result.
 * - followLinks : Whether to follow symbolic links (default: false).
 * - includeDots : Whether to include dot files (default: false).
 * - mode : Filter by type: 'files', 'dirs', or 'both' (default: 'files').
 * - order : Sort order: 'asc' (default) or 'desc'.
 * - pattern : A glob pattern, regex, or list of patterns to match file names.
 * - recursive : Whether to search recursively (default: false).
 * - sort : A sort option, eg: callback, predefined string, or array of keys.
 *
 * @return SplFileInfo[]
 *
 * @throws DirectoryException
 *
 * @example
 * 1. Basic usage: list files in directory
 * ```php
 * use function oihana\files\findFiles;
 * use SplFileInfo;
 *
 * $files = findFiles('/var/www');
 * ```
 *
 * 2. Recursive search
 * ```php
 * $files = findFiles('/var/www', [
 * 'recursive' => true,
 * ]);
 * ```
 * 3. Include dotfiles
 * ```php
 * $files = findFiles('/var/www', [
 * 'includeDots' => true,
 * ]);
 * ```
 *
 * 4. Follow symbolic links (only affects recursive mode)
 * ```php
 * $files = findFiles('/var/www', [
 * 'recursive'   => true,
 * 'followLinks' => true,
 * ]);
 * ```
 *
 * 5. Filter by file name pattern (glob or regex)
 * ```php
 * $files = findFiles('/var/www', [
 * 'pattern' => '*.php',
 * ]);
 * ```
 *
 * 6. Filter by multiple patterns (mixed glob + regex)
 * ```php
 * $files = findFiles('/var/www', [
 * 'pattern' => ['*.php', '/^config\..+$/'],
 * ]);
 * ```
 *
 * 7. List directories only
 * ```php
 * $dirs = findFiles('/var/www', [ 'mode' => 'dirs', ]);
 * ```
 *
 * 8. List both files and directories
 * ```php
 * $all = findFiles('/var/www', [ 'mode' => 'both' ]);
 * ```
 *
 * 9. Custom sort: by real path
 * ```php
 * $files = findFiles('/var/www', [
 * 'sort' => fn(SplFileInfo $a, SplFileInfo $b) => strcmp($a->getRealPath(), $b->getRealPath()),
 * ]);
 * ```
 * 10. Predefined sort (e.g., name), descending order
 * ```php
 * $files = findFiles('/var/www', [
 * 'sort'  => 'name',
 * 'order' => 'desc',
 * ]);
 * ```
 *
 * 11. Combined sort: type then name (directories first)
 * ```php
 * $files = findFiles('/var/www', [
 * 'sort' => ['type', 'name'],
 * ]);
 * ```
 *
 * 12. Map output to base names only
 * ```php
 * $names = findFiles('/var/www', [
 * 'filter' => fn(SplFileInfo $file) => $file->getBasename(),
 * ]);
 *```
 *
 * 13. Get only file sizes
 * ```php
 * $sizes = findFiles('/var/www', [
 * 'filter' => fn(SplFileInfo $file) => $file->getSize(),
 * ]);
 * ```
 *
 * 14. List recursively with all options combined
 * ```php
 * $files = findFiles('/var/www',
 * [
 *     'recursive'    => true,
 *     'followLinks'  => true,
 *     'includeDots'  => true,
 *     'mode'         => 'files',
 *     'pattern'      => ['*.log', '*.txt'],
 *     'sort'         => 'ci_name',
 *     'order'        => 'asc',
 *     'filter'       => fn(SplFileInfo $file) => $file->getFilename(),
 * ]);
 * ```
 * @see sortFiles()
 * @see FindFindOption
 * @see FindMode
 * @see Order::asc
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
function findFiles( ?string $directory , array $options = [] ): array
{
    assertDirectory( $directory );

    $filter      = $options[ FindFilesOption::FILTER       ] ?? null  ;
    $followLinks = $options[ FindFilesOption::FOLLOW_LINKS ] ?? false ;
    $includeDots = $options[ FindFilesOption::INCLUDE_DOTS ] ?? false ;
    $mode        = $options[ FindFilesOption::MODE         ] ?? FindMode::FILES ;
    $order       = $options[ FindFilesOption::ORDER        ] ?? Order::asc ;
    $patterns    = $options[ FindFilesOption::PATTERN      ] ?? null  ;
    $recursive   = $options[ FindFilesOption::RECURSIVE    ] ?? false ;
    $sort        = $options[ FindFilesOption::SORT         ] ?? false ;

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