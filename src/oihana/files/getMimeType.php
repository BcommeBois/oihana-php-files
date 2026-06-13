<?php

namespace oihana\files ;

/**
 * Detects the MIME type of a file using the `finfo` extension.
 *
 * This is the low-level primitive shared by the higher-level MIME helpers
 * ({@see hasMimeType()}, {@see images\getImageMimeType()}, {@see archive\tar\tarFileInfo()},
 * {@see archive\zip\zipFileInfo()}). It returns the raw type reported by
 * `finfo` (e.g. `text/plain`, `application/zip`) without any normalization.
 *
 * The function never throws: it returns `null` when the path is not a file or
 * when detection fails (including an empty result), so callers can decide how
 * to react (a boolean check, a fallback label such as `'unknown'`, etc.).
 *
 * @param string $file Path to the file to inspect.
 *
 * @return string|null The detected MIME type, or `null` if `$file` is not a file or detection failed.
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.2.0
 *
 * @example
 * ```php
 * use function oihana\files\getMimeType;
 *
 * var_dump( getMimeType('/path/to/notes.txt'  ) ); // string(10) "text/plain"
 * var_dump( getMimeType('/path/to/archive.zip') ); // string(15) "application/zip"
 * var_dump( getMimeType('/path/to/missing'    ) ); // NULL
 * ```
 */
function getMimeType( string $file ): ?string
{
    if ( !is_file( $file ) )
    {
        return null ;
    }

    $finfo = finfo_open( FILEINFO_MIME_TYPE ) ;
    if ( $finfo === false )
    {
        // finfo_open does not fail when ext-fileinfo is available (a hard requirement).
        // @codeCoverageIgnoreStart
        return null ;
        // @codeCoverageIgnoreEnd
    }

    $mimeType = finfo_file( $finfo , $file ) ;
    finfo_close( $finfo ) ;

    return $mimeType === false || $mimeType === '' ? null : $mimeType ;
}
