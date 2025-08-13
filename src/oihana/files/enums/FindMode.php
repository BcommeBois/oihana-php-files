<?php

namespace oihana\files\enums;

use oihana\reflect\traits\ConstantsTrait;

/**
 * FileExtension
 *
 * Enumeration class that defines various mode options to use in the findFiles function.
 *
 * @package oihana\files\enums
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
class FindMode
{
    use ConstantsTrait ;

    /**
     * List files and directories.
     */
    public const string BOTH  = 'both' ;

    /**
     * List files only.
     */
    public const string FILES = 'files' ;

    /**
     * List directories only.
     */
    public const string DIRS  = 'dirs' ;

}