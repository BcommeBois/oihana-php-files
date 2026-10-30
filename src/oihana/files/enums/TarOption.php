<?php

namespace oihana\files\enums;

use oihana\reflect\traits\ConstantsTrait;

/**
 * Enumeration of the option keys accepted by the `oihana\files\archive\tar` helpers.
 *
 * The keys serve two functions, and each one reads only its own:
 *
 * - {@see \oihana\files\archive\tar\untar()} reads {@see self::ALLOW_UNSAFE_LINKS},
 *   {@see self::DRY_RUN}, {@see self::KEEP_PERMISSIONS}, {@see self::MAX_EXTRACTED_SIZE} and
 *   {@see self::OVERWRITE}.
 * - {@see \oihana\files\archive\tar\tarDirectory()} reads {@see self::EXCLUDE},
 *   {@see self::FILTER} and {@see self::METADATA}.
 *
 * A key the called function does not read is ignored rather than refused.
 *
 * @package oihana\files\enums
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
class TarOption
{
    use ConstantsTrait ;

    /**
     * Whether to restore a link whose target leaves the output directory.
     *
     * {@see \oihana\files\archive\tar\untar()} refuses, by default, to extract an archive holding
     * a link whose target is absolute or climbs out of the output directory: a link is the one
     * entry whose content is itself a path, and creating one hands every entry written after it a
     * way out. Set this to `true` only for an archive you answer for — your own backup, typically,
     * whose links were absolute on the disk it was taken from.
     *
     * It does not lift the other refusal: an archive read by `PharData`, where there is no GNU tar
     * to read it with, is refused whenever it holds a link at all, since that engine cannot
     * restore one — it writes an empty file in its place.
     *
     * Default: `false`.
     */
    public const string ALLOW_UNSAFE_LINKS = 'allowUnsafeLinks' ;

    /**
     * Return the list of entries instead of extracting them.
     *
     * {@see \oihana\files\archive\tar\untar()} then writes nothing and returns the names of the
     * file entries the archive holds, directory entries excluded. The guards still run, so a dry
     * run reports an archive that could not be extracted by throwing rather than by listing it.
     *
     * Default: `false`.
     */
    public const string DRY_RUN = 'dryRun' ;

    /**
     * List of glob patterns or file names to exclude from the archive.
     *
     * Read by {@see \oihana\files\archive\tar\tarDirectory()} and forwarded to
     * {@see \oihana\files\shouldExcludeFile()}, which matches each pattern against both the full
     * path and the base name.
     *
     * Default: `[]` — nothing is excluded.
     */
    public const string EXCLUDE = 'exclude' ;

    /**
     * Filter the files and directories to archive.
     *
     * A callable `function ( string $filePath ): bool` returning `true` to include the item, read
     * by {@see \oihana\files\archive\tar\tarDirectory()}. Applied after {@see self::EXCLUDE}, so a
     * path already excluded never reaches it.
     *
     * Default: `null` — everything that survived the exclusions is archived.
     */
    public const string FILTER = 'filter' ;

    /**
     * Whether to restore the Unix permissions the archive recorded.
     *
     * {@see \oihana\files\archive\tar\untar()} then applies each entry's stored mode to what it
     * extracted. Best-effort: a mode that cannot be applied, on a file the caller does not own,
     * is left as it is rather than failing the extraction.
     *
     * Default: `false` — the extracted files take the usual default mode.
     */
    public const string KEEP_PERMISSIONS = 'keepPermissions' ;

    /**
     * Maximum total uncompressed size in bytes accepted during extraction.
     *
     * When set to a positive integer, {@see \oihana\files\archive\tar\untar()} runs a pre-scan of
     * the archive and throws {@see \RuntimeException} if the sum of the entries' uncompressed
     * sizes exceeds this limit, **before** any file is written to disk. This guards against
     * decompression-bomb attacks (small archive expanding to gigabytes).
     *
     * Default: `null` — no limit.
     */
    public const string MAX_EXTRACTED_SIZE = 'maxExtractedSize' ;

    /**
     * Additional metadata to embed in the archive as `.metadata.json`.
     *
     * Read by {@see \oihana\files\archive\tar\tarDirectory()}. Embedding metadata also makes the
     * archive non-empty, so a directory where no file matched the filters still produces one,
     * holding `.metadata.json` alone.
     *
     * Default: `[]` — no metadata file is written.
     */
    public const string METADATA = 'metadata' ;

    /**
     * Whether an existing target file may be overwritten.
     *
     * Read by {@see \oihana\files\archive\tar\untar()}: when `false`, the archive is listed first
     * and the extraction is refused, before anything is written, if one of its entries already
     * exists in the output directory. It says nothing about the archive being written, which
     * {@see \oihana\files\archive\tar\tar()} replaces when the path it was given already holds one.
     *
     * Default: `true`.
     */
    public const string OVERWRITE = 'overwrite' ;
}
