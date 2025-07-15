<?php

namespace oihana\files ;

use oihana\enums\Char;
use oihana\files\exceptions\DirectoryException;

/**
 * Normalises (and optionally validates) a directory path.
 *
 * - If <code>$path</code> is an array, empty segments and <code>Char::EMPTY</code> are removed, then the remaining parts are joined with <code>DIRECTORY_SEPARATOR</code>.
 * - If <code>$assertable</code> is true (default), {@see assertDirectory()} ensures the resulting path exists and is readable.
 * - Trailing separators are always stripped before return.
 *
 * @param string|array|null $path Directory or segments to normalise. <br>
 * Examples: <code>'/tmp'</code> or <code>['tmp','logs']</code>.
 * @param bool $assertable Whether to validate the resulting path. Defaults to true.
 * @param bool $isReadable Check if the directory is readable (Default true).
 * @param bool $isWritable Check if the directory is writable (Default false).
 * @return string Normalised directory path.
 * @throws DirectoryException If validation is enabled and the directory is invalid.
 */
function getDirectory( string|array|null $path , bool $assertable = true , bool $isReadable = true , bool $isWritable = false ): string
{
    if ( is_array( $path ) )
    {
        $path = array_filter
        (
            $path ,
            static fn( ?string $p ): bool => is_string( $p ) && $p !== Char::EMPTY
        );
        $path = implode(DIRECTORY_SEPARATOR , $path ) ;
    }

    $path = $path ?? Char::EMPTY;

    if( $assertable )
    {
        assertDirectory( $path , $isReadable , $isWritable ) ;
    }

    return rtrim( $path , DIRECTORY_SEPARATOR ) ;
}