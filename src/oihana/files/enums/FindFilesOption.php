<?php

namespace oihana\files\enums;

use oihana\reflect\traits\ConstantsTrait;
use function oihana\files\findFiles;

/**
 * Enumeration class that defines various options to use in the fileFiles function.
 *
 * @package oihana\files\enums
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 *
 * @see findFiles()
 */
class FindFilesOption
{
    use ConstantsTrait ;

    /**
     * A function to map or transform each SplFileInfo result.
     */
    public const string FILTER = 'filter' ;

    /**
     * Whether to follow symbolic links (default: false).
     */
    public const string FOLLOW_LINKS = 'followLinks' ;

    /**
     * Whether to include dot files (default: false).
     */
    public const string INCLUDE_DOTS = 'includeDots' ;


    /**
     * Filter by type: 'files', 'dirs', or 'both' (default: 'files').
     */
    public const string MODE = 'mode' ;

    /**
     * Sort order: 'asc' (default) or 'desc'.
     */
    public const string ORDER = 'order' ;

    /**
     * A glob pattern, regex, or list of patterns to match file names.
     */
    public const string PATTERN = 'pattern' ;

    /**
     * Whether to search recursively (default: false).
     */
    public const string RECURSIVE = 'recursive' ;

    /**
     * A sort option, eg: callback, predefined string, or array of keys.
     */
    public const string SORT = 'sort' ;
}