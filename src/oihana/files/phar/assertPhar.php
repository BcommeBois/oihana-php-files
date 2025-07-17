<?php

namespace oihana\files ;

use RuntimeException;

/**
 * Asserts that PhpPhar is available.
 * @throws RuntimeException If the PharData extension is not available.
 */
function assertPhar(): void
{
    if ( !class_exists('PharData' ) || !extension_loaded('phar' ) )
    {
        throw new RuntimeException( 'PharData is not available. Please ensure the phar extension is enabled.' ) ;
    }
}