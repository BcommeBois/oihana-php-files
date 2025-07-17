<?php

namespace oihana\files\enums;

use oihana\reflections\traits\ConstantsTrait;

/**
 * FileExtension
 *
 * Enumeration class that defines various mode options to use in the findFiles function.
 *
 * @package oihana\files\enums
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
class TarOption
{
    use ConstantsTrait ;

    /**
     * Return the files in the archive.
     */
    public const string DRY_RUN = 'dryRun' ;

    /**
     * List of glob patterns or file names to exclude.
     */
    public const string EXCLUDE = 'exclude' ;

    /**
     * Filter the files or directories to archive with a function (string $filepath): bool
     */
    public const string FILTER = 'filter' ;

    /**
     * Keep the permissions of the files/directories.
     */
    public const string KEEP_PERMISSIONS = 'keepPermissions' ;

    /**
     * Overwrite the archive.
     */
    public const string OVERWRITE = 'overwrite' ;

    /**
     * Additional metadata to include in `.metadata.json`
     */
    public const string METADATA  = 'metadata' ;
}