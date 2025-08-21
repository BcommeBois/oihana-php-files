<?php

namespace oihana\files\enums\traits;

/**
 * The trait to register all video mime types.
 *
 * This trait provides constants for widely used video MIME types, which
 * can be used to validate or set content-type headers safely.
 *
 * @package oihana\files\enums\traits
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
trait VideoMimeTypeTrait
{
    public const string AVI  = 'video/x-msvideo' ;
    public const string FLV  = 'video/x-flv' ;
    public const array  M4V  = [ 'video/x-m4' , 'video/mp4v' ];
    public const string MKV  = 'video/x-matroska' ;
    public const string MOV  = 'video/quicktime' ;
    public const string MP4  = 'video/mp4' ;
    public const string MPG  = 'video/mpeg' ;
    public const string MPEG = 'video/mpeg' ;
    public const string WEBM = 'video/webm' ;
    public const string WMV  = 'video/x-ms-wmv' ;
}