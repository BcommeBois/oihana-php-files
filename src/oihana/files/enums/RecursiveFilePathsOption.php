<?php

namespace oihana\files\enums;

use oihana\reflect\traits\ConstantsTrait;
use function oihana\files\recursiveFilePaths;

/**
 * Enumeration class that defines various options to use in the recursiveFilePaths function.
 *
 * @package oihana\files\enums
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 *
 * @see recursiveFilePaths()
 */
class RecursiveFilePathsOption
{
    use ConstantsTrait ;

    /**
     * The enumeration of all files to excludes.
     */
    public const string EXCLUDES = 'excludes' ;

    /**
     * The optional list of the extensions to use to scan the folder(s).
     */
    public const string EXTENSIONS = 'extensions' ;

    /**
     * The maximum allowed depth. Default -1 is used.
     */
    public const string MAX_DEPTH = 'maxDepth' ;

    /**
     * Indicates if the list of file paths is sorted before returned.
     */
    public const string SORTABLE = 'sortable' ;
}