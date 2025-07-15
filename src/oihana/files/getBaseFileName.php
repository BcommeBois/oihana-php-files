<?php

namespace oihana\files ;

use InvalidArgumentException;

use oihana\enums\Char;
use oihana\files\enums\FileExtension;
use function oihana\core\strings\lower;

/**
 * Returns the base file name without its extension from a given file path.
 *
 * This function extracts the file name from a full path and removes its extension.
 * It supports both single and multi-part extensions (e.g. `.tar.gz`, `.blade.php`).
 *
 * @param string $file The full path to the file (e.g. '/path/to/archive.tar.gz').
 * @param array|null $multiplePartExtensions Optional list of multi-part extensions to consider
 *                                           (e.g. ['.tar.gz', '.blade.php']).
 *                                           If null, the method defaults to
 *                                           FileExtension::getMultiplePartExtensions().
 *
 * @return string The file name without its extension (e.g. 'archive' for 'archive.tar.gz').
 *
 * @throws InvalidArgumentException If the file path is empty or invalid.
 * @see FileExtension
 */
function getBaseFileName( string $file , ?array $multiplePartExtensions = null ): string
{
    if ( empty( $file ) )
    {
        throw new InvalidArgumentException('The file path cannot be empty.') ;
    }

    $file = str_replace(Char::BACK_SLASH , Char::SLASH , $file ) ;

    if ( is_dir( $file ) || str_ends_with( $file , Char::SLASH ) )
    {
        throw new InvalidArgumentException('The file path is invalid or points to a directory.');
    }

    $fileNameWithExtension = basename( $file ) ;
    if ( empty( $fileNameWithExtension ) )
    {
        throw new InvalidArgumentException('The file path is invalid.' ) ;
    }

    $parts = explode(Char::DOT , $fileNameWithExtension ) ;
    $count = count( $parts ) ;

    if( $count === 1 )
    {
        return $fileNameWithExtension ;
    }

    $multiplePartExtensions = $multiplePartExtensions ?? FileExtension::getMultiplePartExtensions() ;

    foreach ( $multiplePartExtensions as $extension )
    {
        $length = strlen( $extension );
        if ( strtolower( substr( $fileNameWithExtension , -$length ) ) === lower( $extension ) )
        {
            return substr( $fileNameWithExtension , 0 , -$length ) ;
        }
    }

    $lastDotPos = strrpos($fileNameWithExtension, Char::DOT ) ;

    return $lastDotPos === false ? $fileNameWithExtension : substr( $fileNameWithExtension , 0 , $lastDotPos ) ;
}