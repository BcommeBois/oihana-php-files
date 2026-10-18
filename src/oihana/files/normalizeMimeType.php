<?php

namespace oihana\files ;

use oihana\files\enums\MimeTypeAlias ;

/**
 * Brings a MIME type name back to the reference name of its format.
 *
 * The name is lower-cased, its parameters are dropped (`audio/wav; codecs=1` gives `audio/wav`),
 * and an alias listed in {@see MimeTypeAlias::ALIASES} becomes the name the catalogue uses
 * (`audio/x-wav` gives `audio/wav`). A name the table does not list comes back lower-cased and
 * without parameters, unchanged otherwise.
 *
 * Two names of the same format then compare equal, whichever one `finfo` reports on a given host
 * and whichever one a caller wrote : {@see enums\FileMimeType::getExtension()} and
 * {@see validateMimeType()} compare normalized names.
 *
 * @param string $mimeType The MIME type name, as reported by `finfo` or declared by a client.
 *
 * @return string The reference name of the format, or the cleaned name when it has no alias.
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.4.0
 *
 * @example
 * ```php
 * use function oihana\files\normalizeMimeType;
 *
 * normalizeMimeType( 'audio/x-wav'               ) ; // 'audio/wav'
 * normalizeMimeType( 'Text/XML; charset=utf-8'   ) ; // 'application/xml'
 * normalizeMimeType( 'image/png'                 ) ; // 'image/png'
 * normalizeMimeType( 'application/x-unknown'     ) ; // 'application/x-unknown'
 * ```
 */
function normalizeMimeType( string $mimeType ) :string
{
    $mimeType = strtolower( trim( explode( ';' , $mimeType , 2 )[ 0 ] ) ) ;

    return MimeTypeAlias::ALIASES[ $mimeType ] ?? $mimeType ;
}
