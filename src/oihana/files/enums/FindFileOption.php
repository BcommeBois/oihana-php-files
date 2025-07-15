<?php

namespace oihana\files\enums;

use oihana\reflections\traits\ConstantsTrait;

class FindFileOption
{
    use ConstantsTrait ;

    /**
     * The optional function to filter all files.
     */
    public const string FILTER  = 'filter' ;

    /**
     * Indicates whether symbolic links should be followed.
     */
    public const string FOLLOW_LINKS = 'followLinks' ;

    /**
     * Indicates if the dot files are included.
     */
    public const string INCLUDE_DOTS = 'includeDots' ;

    /**
     * Filter mode ("files", "dirs" or "both").
     */
    public const string MODE = 'mode' ;

    /**
     * The order of the file sorting : default 'asc' or 'desc'.
     */
    public const string ORDER = 'order' ;

    /**
     * A pattern (a regexp, a glob, or a string) or an array of patterns.
     */
    public const string PATTERN = 'pattern' ;

    /**
     * Indicates if all sub-directories are browsed.
     */
    public const string RECURSIVE = 'recursive' ;

    /**
     * The optional sort option to sort all files.
     */
    public const string SORT = 'sort' ;
}