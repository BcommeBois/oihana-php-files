<?php

namespace oihana\files ;

use oihana\enums\Char;
use function oihana\core\strings\lower;

/**
 * Retrieves the file extension (including multipart extensions) from a given file path.
 *
 * This function extracts the file extension from the filename portion of the path,
 * supporting both simple extensions (e.g. `.txt`) and multipart extensions (e.g. `.tar.gz`, `.blade.php`).
 * It relies on the `getBaseFileName()` function to determine the filename without its extension,
 * then returns the remainder as the extension.
 *
 * The function normalizes Windows-style backslashes (`\`) to forward slashes (`/`) before processing.
 *
 * @param string $file The full path or filename from which to extract the extension.
 * @param array|null $multiplePartExtensions Optional array of multipart extensions to consider.
 *                                          If null, the default set from `FileExtension::getMultiplePartExtensions()` is used.
 * @param bool $lowercase Enforce the extension to be lowercase (default true).
 *
 * @return string|null The file extension including the leading dot (e.g. `.tar.gz`),
 *                     or null if the file has no extension.
 *
 * @example
 *
 * Basic usage: extract extension from a file path.
 * ```php
 * use function oihana\files\getFileExtension;
 *
 * echo getFileExtension('/path/to/archive.tar.gz');    // .tar.gz
 * echo getFileExtension('photo.JPG');                  // .jpg (lowercased by default)
 * echo getFileExtension('/some/file.txt');             // .txt
 * echo getFileExtension('/templates/home.blade.php');  // .blade.php
 * echo getFileExtension('script.min.js');              // .js
 * ```
 *
 * Using custom multipart extensions:
 * ```php
 * $custom = ['.custom.ext', '.tpl.php'];
 *
 * echo getFileExtension('file.custom.ext', $custom);   // .custom.ext
 * echo getFileExtension('file.tpl.php', $custom);      // .tpl.php
 * ```
 *
 * Preserving original case:
 * ```php
 * echo getFileExtension('README.MD', null, false);     // .MD
 * ```
 *
 * Files with no extension:
 * ```php
 * echo getFileExtension('Makefile');                   // null
 * echo getFileExtension('.env');                       // null
 * ```
 *
 * Windows-style path normalization:
 * ```php
 * echo getFileExtension('C:\\projects\\demo.tar.bz2'); // .tar.bz2
 * ```
 *
 * Edge case: file with multiple dots and unknown multipart extension:
 * ```php
 * echo getFileExtension('data.backup.final.bak');      // .bak
 * ```
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
function getFileExtension( string $file , ?array $multiplePartExtensions = null , bool $lowercase = true ): ?string
{
    $base = getBaseFileName( $file , $multiplePartExtensions );
    $file = basename( str_replace('\\' , '/' , $file ) ); // normaliser chemin Windows
    $ext  = substr( $file, strlen($base ) ) ;

    if( $ext !== Char::EMPTY )
    {
        return $lowercase ? lower( $ext ) : $ext ;
    }

    return null ;
}