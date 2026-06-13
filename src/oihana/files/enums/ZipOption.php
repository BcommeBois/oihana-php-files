<?php

namespace oihana\files\enums;

use oihana\reflect\traits\ConstantsTrait;

/**
 * Enumeration of the option keys accepted by the `oihana\files\archive\zip` helpers
 * ({@see \oihana\files\archive\zip\zip()}, {@see \oihana\files\archive\zip\zipDirectory()}
 * and {@see \oihana\files\archive\zip\unzip()}).
 *
 * @package oihana\files\enums
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.2.0
 */
class ZipOption
{
    use ConstantsTrait ;

    /**
     * Compression method applied to each entry when creating an archive.
     *
     * Accepts {@see CompressionType::ZIP} (DEFLATE, the default) or
     * {@see CompressionType::NONE} (stored, no compression).
     */
    public const string COMPRESSION = 'compression' ;

    /**
     * Return the list of entries instead of extracting them.
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
     * Keep the Unix permissions of the files/directories.
     */
    public const string KEEP_PERMISSIONS = 'keepPermissions' ;

    /**
     * Maximum number of entries allowed in the archive (decompression-bomb guard).
     *
     * When set to a positive integer, {@see \oihana\files\archive\zip\unzip()} rejects
     * archives that declare more entries than this limit, **before** extracting anything.
     * Default: `null` — no limit.
     */
    public const string MAX_ENTRIES = 'maxEntries' ;

    /**
     * Maximum total uncompressed size in bytes accepted during extraction (decompression-bomb guard).
     *
     * When set to a positive integer, {@see \oihana\files\archive\zip\unzip()} pre-scans the
     * archive and aborts if the sum of the entries' uncompressed sizes exceeds this limit,
     * **before** any file is written to disk. Default: `null` — no limit.
     */
    public const string MAX_SIZE = 'maxSize' ;

    /**
     * Additional metadata to include in `.metadata.json`
     */
    public const string METADATA = 'metadata' ;

    /**
     * Whether an existing target file may be overwritten.
     */
    public const string OVERWRITE = 'overwrite' ;
}
