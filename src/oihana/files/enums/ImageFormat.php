<?php

namespace oihana\files\enums;

use oihana\reflect\traits\ConstantsTrait;

/**
 * Enumeration that defines various image formats supported by the application.
 *
 * Ex: ImageFormat::AVIF -> 'avif'
 *
 * @package oihana\files\enums
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
class ImageFormat
{
    use ConstantsTrait ;

    public const string AVIF  = 'avif'  ;
    public const string BMP   = 'bmp'   ;
    public const string CUR   = 'cur'   ;
    public const string GIF   = 'gif'   ;
    public const string HEIC  = 'heic'  ;
    public const string HEIF  = 'heif'  ;
    public const string ICO   = 'ico'   ;
    public const string JPEG  = 'jpeg'  ;
    public const string JPG   = 'jpg'   ;
    public const string PNG   = 'png'   ;
    public const string SVG   = 'svg'   ;
    public const string TIF   = 'tif'   ;
    public const string TIFF  = 'tiff'  ;
    public const string WEBP  = 'webp'  ;
}