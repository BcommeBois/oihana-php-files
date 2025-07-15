<?php

namespace oihana\files ;

use RuntimeException;

use function oihana\core\arrays\deepMerge;

/**
 * Requires multiple PHP files (each returning an array) and merges the results.
 * @param array $filePaths An array of absolute or relative file paths to load.
 * @param bool $recursive Whether to perform a deep (recursive) merge (true) or a simple merge (false).
 * @return array The merged array.
 * @throws RuntimeException If a specified file is missing or does not return an array.
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