<?php

use oihana\files\enums\ImageFormat;
use oihana\files\enums\ImageMimeType;
use oihana\files\exceptions\FileException;

use function oihana\files\assertFile;

/**
 * Resolves and validates the MIME type of a file, typically an image.
 *
 * This function ensures that the returned MIME type is safe and consistent.
 * Optionally, it can validate a provided `$format` (file extension) against a whitelist
 * of allowed formats and ensure that it matches the actual MIME type of the file.
 *
 * Behavior:
 * 1. If `$format` is provided and exists in `$allowedFormats`:
 * - Returns the corresponding MIME type if it matches the detected MIME.
 * - Otherwise, falls back to the actual detected MIME type.
 * 2. If `$format` is null or not in `$allowedFormats`, the detected MIME is returned.
 *
 * @param string $file Absolute path to the file to check.
 * @param string|null $format Optional desired format (file extension without dot),
 * e.g., "jpg", "png", "webp".
 * @param array $allowedFormats Optional whitelist mapping file extensions to MIME types.
 * Defaults to common image formats:
 * ```php
 * [
 *     'jpg'  => 'image/jpeg',
 *     'jpeg' => 'image/jpeg',
 *     'png'  => 'image/png',
 *     'gif'  => 'image/gif',
 *     'webp' => 'image/webp',
 *     'svg'  => 'image/svg+xml',
 *     'avif' => 'image/avif',
 * ]
 * ```
 *
 * @return string The resolved MIME type of the file.
 *
 * @throws InvalidArgumentException If the file does not exist or is not readable.
 * @throws FileException            If the file cannot be validated.
 *
 * @example
 * ```php
 * $mime = getImageMimeType('/path/to/image.png'); // returns 'image/png'
 * $mime = getImageMimeType('/path/to/image.jpg', 'jpg'); // returns 'image/jpeg'
 * ```
 *
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 * @package oihana\files\path
 */
function getImageMimeType
(
    string  $file  ,
    ?string $format = null ,
    array   $allowedFormats =
    [
        ImageFormat::AVIF => ImageMimeType::AVIF ,
        ImageFormat::JPG  => ImageMimeType::JPG  ,
        ImageFormat::JPEG => ImageMimeType::JPEG ,
        ImageFormat::PNG  => ImageMimeType::PNG  ,
        ImageFormat::GIF  => ImageMimeType::GIF  ,
        ImageFormat::SVG  => ImageMimeType::SVG  ,
        ImageFormat::WEBP => ImageMimeType::WEBP ,
    ]
) :string
{
    assertFile( $file );

    $format = $format !== null ? strtolower(trim($format)) : null;

    $finfo    = finfo_open(FILEINFO_MIME_TYPE ) ;
    $realMime = finfo_file( $finfo , $file ) ;

    finfo_close( $finfo ) ;

    if ( $format && isset( $allowedFormats[ $format ] ) )
    {
        $expectedMime = $allowedFormats[ $format ] ;
        if ( str_contains( $realMime , $expectedMime ) )
        {
            return $expectedMime ;
        }
    }

    return $realMime;
}