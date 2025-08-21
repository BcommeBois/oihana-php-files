<?php

namespace oihana\files ;

use oihana\files\exceptions\FileException;

/**
 * Clears the content of a file while keeping the file itself.
 *
 * @param string|null $file The full path to the file to clear.
 *
 * @return void
 *
 * @throws FileException If the file does not exist or is not writable.
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
function clearFile(?string $file): void
{
    assertFile( $file , isWritable: true ) ;

    $handle = @fopen( $file, 'w' ) ;
    if ($handle === false)
    {
        throw new FileException("Unable to open file '$file' for writing.");
    }

    fclose($handle);
}