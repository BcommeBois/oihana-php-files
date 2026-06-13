<?php

namespace oihana\files\enums;

use oihana\reflect\traits\ConstantsTrait;

/**
 * Enumeration of the option keys accepted by the `findFiles` function.
 *
 * @deprecated 1.2.0 Use {@see FindFilesOption} instead. This class is a thin,
 * value-identical alias kept for backward compatibility: every constant simply
 * mirrors its {@see FindFilesOption} counterpart. It will be removed in a future
 * major release.
 *
 * @package oihana\files\enums
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 *
 * @see FindFilesOption
 */
class FindFileOption
{
    use ConstantsTrait ;

    /**
     * The optional function to filter all files.
     * @deprecated 1.2.0 Use {@see FindFilesOption::FILTER}.
     */
    public const string FILTER  = FindFilesOption::FILTER ;

    /**
     * Indicates whether symbolic links should be followed.
     * @deprecated 1.2.0 Use {@see FindFilesOption::FOLLOW_LINKS}.
     */
    public const string FOLLOW_LINKS = FindFilesOption::FOLLOW_LINKS ;

    /**
     * Indicates if the dot files are included.
     * @deprecated 1.2.0 Use {@see FindFilesOption::INCLUDE_DOTS}.
     */
    public const string INCLUDE_DOTS = FindFilesOption::INCLUDE_DOTS ;

    /**
     * Filter mode ("files", "dirs" or "both").
     * @deprecated 1.2.0 Use {@see FindFilesOption::MODE}.
     */
    public const string MODE = FindFilesOption::MODE ;

    /**
     * The order of the file sorting : default 'asc' or 'desc'.
     * @deprecated 1.2.0 Use {@see FindFilesOption::ORDER}.
     */
    public const string ORDER = FindFilesOption::ORDER ;

    /**
     * A pattern (a regexp, a glob, or a string) or an array of patterns.
     * @deprecated 1.2.0 Use {@see FindFilesOption::PATTERN}.
     */
    public const string PATTERN = FindFilesOption::PATTERN ;

    /**
     * Indicates if all sub-directories are browsed.
     * @deprecated 1.2.0 Use {@see FindFilesOption::RECURSIVE}.
     */
    public const string RECURSIVE = FindFilesOption::RECURSIVE ;

    /**
     * The optional sort option to sort all files.
     * @deprecated 1.2.0 Use {@see FindFilesOption::SORT}.
     */
    public const string SORT = FindFilesOption::SORT ;
}
