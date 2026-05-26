<?php

namespace oihana\files ;

use InvalidArgumentException;
use RuntimeException;

use function oihana\core\arrays\deepMerge;
use function oihana\files\path\isBasePath;

/**
 * Requires multiple PHP files (each returning an array) and merges the results.
 *
 * Each path goes through a defensive validation pipeline before `require`:
 * 1. The path must be a non-empty string.
 * 2. It must resolve via {@see realpath()} to an existing regular file.
 * 3. The file extension must be `.php` (case-insensitive).
 * 4. If `$allowedBase` is provided, the resolved file must be located inside
 *    that base directory (defense in depth against path-escape attacks).
 *
 * This protects against arbitrary file inclusion when paths come from untrusted
 * or semi-trusted sources. Note that even with `$allowedBase`, callers must still
 * trust the **content** of the included files — `require` executes their PHP code.
 *
 * @param array       $filePaths   An array of file paths to load.
 * @param bool        $recursive   Whether to perform a deep (recursive) merge (true) or a simple merge (false).
 * @param string|null $allowedBase Optional absolute directory path. When provided, every file in
 *                                 `$filePaths` must be located inside this directory after canonicalisation.
 *                                 Strongly recommended when paths are not 100% trusted at the call site.
 *
 * @return array The merged array.
 *
 * @throws InvalidArgumentException If `$allowedBase` is provided but does not resolve to a valid directory.
 * @throws RuntimeException         If a path is not a non-empty string, does not resolve to an existing
 *                                  `.php` file, escapes `$allowedBase`, or does not return an array.
 *
 * @example
 * ```php
 * use function oihana\files\requireAndMergeArrays;
 *
 * $paths = [
 *     __DIR__ . '/config/default.php',
 *     __DIR__ . '/config/override.php',
 * ];
 *
 * // Basic usage — relies on the caller to trust $paths.
 * $config = requireAndMergeArrays($paths);
 *
 * // Shallow merge.
 * $config = requireAndMergeArrays($paths, false);
 *
 * // Hardened usage — every file must be under __DIR__/config.
 * $config = requireAndMergeArrays($paths, true, __DIR__ . '/config');
 * ```
 *
 * Example of a required file:
 * ```php
 * // config/default.php
 * return [
 *     'app' => [
 *         'debug'    => false,
 *         'timezone' => 'UTC',
 *     ],
 * ];
 * ```
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
function requireAndMergeArrays( array $filePaths , bool $recursive = true , ?string $allowedBase = null ): array
{
    $result = [];

    $allowedBaseReal = null ;
    if ( $allowedBase !== null )
    {
        $allowedBaseReal = realpath( $allowedBase ) ;
        if ( $allowedBaseReal === false || !is_dir( $allowedBaseReal ) )
        {
            throw new InvalidArgumentException( sprintf
            (
                'The allowedBase "%s" is not a valid directory.' ,
                $allowedBase
            )) ;
        }
    }

    foreach ( $filePaths as $path )
    {
        if ( !is_string( $path ) || trim( $path ) === '' )
        {
            throw new RuntimeException('Each file path must be a non-empty string.') ;
        }

        $real = realpath( $path ) ;
        if ( $real === false || !is_file( $real ) )
        {
            throw new RuntimeException( sprintf('The file "%s" was not found.' , $path ) ) ;
        }

        if ( strtolower( pathinfo( $real , PATHINFO_EXTENSION ) ) !== 'php' )
        {
            throw new RuntimeException( sprintf('The file "%s" is not a PHP file (.php expected).' , $real ) ) ;
        }

        if ( $allowedBaseReal !== null && !isBasePath( $allowedBaseReal , $real ) )
        {
            throw new RuntimeException( sprintf
            (
                'The file "%s" is outside the allowed base "%s".' ,
                $real ,
                $allowedBaseReal
            )) ;
        }

        $data = require $real ;

        if ( !is_array( $data ) )
        {
            throw new RuntimeException( sprintf( 'The file "%s" did not return an array.' , $real ) ) ;
        }

        $result = $recursive ? deepMerge( $result , $data ) : array_merge( $result , $data ) ;
    }

    return $result ;
}
