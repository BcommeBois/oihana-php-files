<?php

namespace oihana\files\enums;

use oihana\reflections\traits\ConstantsTrait;

/**
 * Enumeration class that defines various options to use in the makeFile function.
 *
 * @package oihana\files\enums
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
class MakeDirectoryOption
{
    use ConstantsTrait ;

    /**
     * Group name or ID to set as file group owner. Default: null.
     */
    public const string GROUP = 'group' ;

    /**
     * User name or ID to set as file owner. Default: null.
     */
    public const string OWNER = 'owner' ;

    /**
     * The path of the directory to create.
     */
    public const string PATH = 'path' ;

    /**
     * The permissions to set for the directory (default: 0755).
     */
    public const string PERMISSIONS = 'permissions' ;

    /**
     * If true, creates parent directories as needed (default: true).
     */
    public const string RECURSIVE = 'recursive' ;
}