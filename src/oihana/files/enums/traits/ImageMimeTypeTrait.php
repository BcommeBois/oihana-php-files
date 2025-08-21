<?php

namespace oihana\files\enums\traits;

/**
 * The trait to register all image mime types.
 *
 * This trait provides constants for widely used image MIME types, which
 * can be used to validate or set content-type headers safely.
 *
 * @package oihana\files\enums\traits
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
trait ImageMimeTypeTrait
{
    public const string AVIF  = 'image/avif';
    public const string BMP   = 'image/bmp';
    public const string CUR   = 'image/x-icon';
    public const string GIF   = 'image/gif';
    public const string HEIC  = 'image/heic';
    public const string HEIF  = 'image/heif';
    public const string ICO   = 'image/vnd.microsoft.icon';
    public const string JPEG  = 'image/jpeg';
    public const string JPG   = 'image/jpeg';
    public const string PNG   = 'image/png';
    public const string SVG   = 'image/svg+xml';
    public const string TIF   = 'image/tiff';
    public const string TIFF  = 'image/tiff';
    public const string WEBP  = 'image/webp';
}