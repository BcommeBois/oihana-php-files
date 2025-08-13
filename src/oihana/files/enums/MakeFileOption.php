<?php

namespace oihana\files\enums;

use oihana\reflect\traits\ConstantsTrait;

/**
 * Enumeration class that defines various options to use in the makeFile function.
 *
 * @package oihana\files\enums
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
class MakeFileOption
{
    use ConstantsTrait ;

    /**
     * If true, appends content instead of overwriting. Default: false.
     */
    public const string APPEND = 'append' ;

    /**
     * The content to write into the file.
     */
    public const string CONTENT = 'content' ;

    /**
     * The path of the file to create or modify.
     */
    public const string FILE = 'file' ;

    /**
     * If true, creates parent directories if they do not exist. Default: true.
     */
    public const string FORCE = 'force' ;

    /**
     * Group name or ID to set as file group owner. Default: null.
     */
    public const string GROUP = 'group' ;

    /**
     * If true, uses an exclusive lock while writing. Default: true.
     */
    public const string LOCK = 'lock' ;

    /**
     * If true, overwrites existing files. Default: false.
     */
    public const string OVERWRITE = 'overwrite' ;

    /**
     * File permissions to set (octal). Default: 0644.
     */
    public const string PERMISSIONS = 'permissions' ;

    /**
     * User name or ID to set as file owner. Default: null.
     */
    public const string OWNER = 'owner' ;
}