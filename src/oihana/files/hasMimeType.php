<?php

namespace oihana\files ;

use function oihana\core\arrays\toArray;

/**
 * Checks whether a file's MIME type matches one of the given MIME types.
 *
 * The file's MIME type is detected with `finfo` and compared to each entry of
 * `$mimeTypes` using a **substring** match (via {@see str_contains()}), so a partial
 * type such as `application/zip` matches a detected `application/zip; charset=binary`.
 *
 * @param string          $filePath  Path to the file.
 * @param string|string[] $mimeTypes A single MIME type, or a list of MIME types (or fragments) to match against.
 *
 * @return bool True if the file exists and its detected MIME type contains one of `$mimeTypes`.
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.2.0
 *
 * @example
 * ```php
 * use function oihana\files\hasMimeType;
 *
 * var_dump( hasMimeType('/path/to/archive.zip', 'application/zip'   ) ); // bool(true)
 * var_dump( hasMimeType('/path/to/archive.zip', ['application/zip'] ) ); // bool(true)
 * var_dump( hasMimeType('/path/to/notes.txt'  , ['application/zip'] ) ); // bool(false)
 * var_dump( hasMimeType('/path/to/missing'    , ['application/zip'] ) ); // bool(false)
 * ```
 */
function hasMimeType( string $filePath , string|array $mimeTypes ): bool
{
    if ( !is_file( $filePath ) )
    {
        return false;
    }

    $mimeTypes = toArray( $mimeTypes ) ;

    $info = finfo_open( FILEINFO_MIME_TYPE );
    if ( $info === false )
    {
        // finfo_open does not fail when ext-fileinfo is available (a hard requirement).
        // @codeCoverageIgnoreStart
        return false;
        // @codeCoverageIgnoreEnd
    }

    $mimeType = finfo_file( $info , $filePath );
    finfo_close( $info );

    if ( $mimeType === false )
    {
        // finfo_file does not return false for an existing, readable file.
        // @codeCoverageIgnoreStart
        return false;
        // @codeCoverageIgnoreEnd
    }

    return array_any( $mimeTypes , fn( $validType ) => str_contains( $mimeType , $validType ) );
}
