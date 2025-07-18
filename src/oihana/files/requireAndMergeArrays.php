<?php

namespace oihana\files ;

use RuntimeException;

use function oihana\core\arrays\deepMerge;

/**
 * Requires multiple PHP files (each returning an array) and merges the results.
 *
 * @param array $filePaths An array of absolute or relative file paths to load.
 * @param bool $recursive Whether to perform a deep (recursive) merge (true) or a simple merge (false).
 *
 * @return array The merged array.
 *
 * @throws RuntimeException If a specified file is missing or does not return an array.
 *
 * @example
 * ```php
 * use function oihana\files\requireAndMergeArrays;
 *
 * $paths = [
 * __DIR__ . '/config/default.php',
 * __DIR__ . '/config/override.php',
 * ];
 *
 * $config = requireAndMergeArrays($paths);
 * print_r($config);
 * ```
 *
 * Shallow merge (non-recursive):
 * ```php
 * $config = requireAndMergeArrays($paths, false);
 * ```
 *
 * Example of a required file:
 * ```php
 * // config/default.php
 * return
 * [
 *     'app' =>
 *     [
 *         'debug' => false,
 *         'timezone' => 'UTC',
 *     ],
 * ];
 *
 * // config/override.php
 * return
 * [
 *     'app' =>
 *     [
 *         'debug' => true,
 *     ],
 * ];
 * ```
 *
 * Result with recursive merge:
 * ```php
 * [
 *     'app' =>
 *     [
 *         'debug'   => true,
 *         'timezone'=> 'UTC',
 *     ],
 * ]
 * ```
 *
 * Error handling:
 * ```php
 * try
 * {
 *     $config = requireAndMergeArrays(['missing.php']);
 * }
 * catch ( RuntimeException $e )
 * {
 *     echo "Error: " . $e->getMessage();
 * }
 * ```
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
function requireAndMergeArrays( array $filePaths , bool $recursive = true ): array
{
    $result = [];

    foreach ( $filePaths as $path )
    {
        if ( !file_exists( $path ) )
        {
            throw new RuntimeException( sprintf('The file "%s" was not found.' , $path ) );
        }

        $data = require $path;

        if ( !is_array( $data ) )
        {
            throw new RuntimeException( sprintf( 'The file "%s" did not return an array.' , $path ) );
        }

        $result = $recursive ? deepMerge( $result , $data ) : array_merge( $result , $data ) ;
    }

    return $result;
}