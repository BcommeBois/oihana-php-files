<?php

namespace oihana\files ;

use oihana\enums\Char;
use oihana\files\exceptions\DirectoryException;

/**
 * Builds a path inside the system temporary directory.
 *
 * @param string|string[]|null $path Optional sub‑path(s) to append inside sys_get_temp_dir().
 * @param bool $assertable Whether to validate the final directory path. Defaults to false because the directory may not exist yet.
 * @param bool $isReadable Check if the directory is readable (Default true).
 * @param bool $isWritable Check if the directory is writable (Default false).
 *
 * @return string Normalised temporary directory path.
 *
 * @throws DirectoryException If validation is enabled and the path is invalid.
 */
function getTemporaryDirectory( string|array|null $path = null , bool $assertable = false , bool $isReadable = true , bool $isWritable = false ): string
{
    $base = sys_get_temp_dir() ;

    if ( is_array( $path ) )
    {
        $path = array_filter($path, static fn(?string $p): bool => is_string($p) && $p !== Char::EMPTY);
        $path = implode(DIRECTORY_SEPARATOR, $path);
    }

    if ( $path !== null && $path !== Char::EMPTY )
    {
        if ( DIRECTORY_SEPARATOR === Char::SLASH && str_starts_with($path, DIRECTORY_SEPARATOR ))
        {
            $path = rtrim($path, DIRECTORY_SEPARATOR); // Unix absolute path
        }
        else if ( DIRECTORY_SEPARATOR === Char::BACK_SLASH && preg_match('#^[A-Z]:\\\\#i', $path))
        {

            $path = rtrim($path, DIRECTORY_SEPARATOR); // Windows absolute path (ex: C:\foo)
        }
        else
        {
            $path = rtrim($base . DIRECTORY_SEPARATOR . $path, DIRECTORY_SEPARATOR);
        }
    }
    else
    {
        $path = $base ;
    }

    return getDirectory( $path , $assertable , $isReadable , $isWritable ) ;
}