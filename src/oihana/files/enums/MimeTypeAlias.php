<?php

namespace oihana\files\enums;

/**
 * The other names a file format is known by, each mapped to the name {@see FileMimeType} uses.
 *
 * A format often carries several MIME type names : `finfo` reports `audio/x-wav` for a WAV file
 * where the catalogue knows `audio/wav`, `text/xml` for an XML document where it knows
 * `application/xml`. Read as different strings, the same file would get no extension from
 * {@see FileMimeType::getExtension()} and would be refused by
 * {@see \oihana\files\validateMimeType()} against a list naming the format the other way.
 * {@see \oihana\files\normalizeMimeType()} reads this table to bring every name back to one.
 *
 * Each alias names the **same format** as its reference : normalizing never lets one format pass
 * for another. The table is a constant of its own class, not of {@see FileMimeType} : the
 * constants of a class using `ConstantsTrait` are all read as MIME types.
 *
 * @package oihana\files\enums
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.4.0
 */
class MimeTypeAlias
{
    /**
     * The alias names, lower-case, each mapped to its reference name.
     *
     * @var array<string, string>
     */
    public const array ALIASES =
    [
        // Reported by finfo for these formats (measured on PHP 8.4, fileinfo 8.4).
        'audio/x-hx-aac-adts' => FileMimeType::AAC ,
        'audio/x-wav'         => FileMimeType::WAV ,
        'text/xml'            => FileMimeType::XML ,

        // Declared by browsers for a voice recording : finfo reports `video/webm` for the same
        // container, which holds sound only or sound and pictures alike.
        'audio/webm'          => FileMimeType::WEBM ,

        // Older names, still reported by some magic databases or declared by some clients.
        'application/x-gzip'  => FileMimeType::GZ   ,
        'audio/vnd.wave'      => FileMimeType::WAV  ,
        'audio/wave'          => FileMimeType::WAV  ,
        'audio/x-aac'         => FileMimeType::AAC  ,
        'audio/x-flac'        => FileMimeType::FLAC ,
        'image/x-ms-bmp'      => FileMimeType::BMP  ,
    ] ;

    /**
     * The type `finfo` reports for a content it does not recognize : bytes, with no format to tell.
     */
    public const string UNKNOWN_BINARY = 'application/octet-stream' ;
}
