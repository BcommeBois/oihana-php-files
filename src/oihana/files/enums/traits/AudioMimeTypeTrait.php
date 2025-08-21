<?php

namespace oihana\files\enums\traits;

/**
 * The trait to register all audio mime types.
 *
 * This trait provides constants for widely used audio MIME types, which
 * can be used to validate or set content-type headers safely.
 *
 * @package oihana\files\enums\traits
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
trait AudioMimeTypeTrait
{
    public const string AAC  = 'audio/aac' ;
    public const string FLAC = 'audio/flac' ;
    public const array  M4A  = [ 'audio/mp4', 'audio/x-m4a' ] ;
    public const string MP3  = 'audio/mpeg' ;
    public const string OGG  = 'audio/ogg' ;
    public const string WAV  = 'audio/wav' ;
    public const string WMA  = 'audio/x-ms-wma' ;
}