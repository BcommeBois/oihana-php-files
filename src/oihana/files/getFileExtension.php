<?php

namespace oihana\files ;

use oihana\enums\Char;

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
 *
 * @return string|null The file extension including the leading dot (e.g. `.tar.gz`),
 *                     or null if the file has no extension.
 */
function getFileExtension( string $file , ?array $multiplePartExtensions = null ): ?string
{
    $base = getBaseFileName( $file , $multiplePartExtensions );
    $file = basename( str_replace('\\' , '/' , $file ) ); // normaliser chemin Windows
    $ext  = substr( $file, strlen($base ) ) ;
    return $ext !== Char::EMPTY ? strtolower( $ext ) : null ;
}