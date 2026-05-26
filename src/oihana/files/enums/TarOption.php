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
     * Maximum total uncompressed size in bytes accepted during extraction.
     *
     * When set to a positive integer, {@see \oihana\files\archive\tar\untar()}
     * runs a pre-scan of the archive and throws {@see \RuntimeException} if the
     * sum of the entries' uncompressed sizes exceeds this limit, **before** any
     * file is written to disk. This guards against decompression-bomb attacks
     * (small archive expanding to gigabytes).
     *
     * Default: `null` — no limit (backward compatible).
     */
    public const string MAX_EXTRACTED_SIZE = 'maxExtractedSize' ;

    /**
     * Overwrite the archive.
     */
    public const string OVERWRITE = 'overwrite' ;

    /**
     * Additional metadata to include in `.metadata.json`
     */
    public const string METADATA  = 'metadata' ;
}