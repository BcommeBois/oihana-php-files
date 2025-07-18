<?php

namespace oihana\files\phar ;

use RuntimeException;


/**
 * Ensures that the `PharData` class and `phar` extension are available in the PHP environment.
 *
 * This function is typically used as a safeguard before attempting to work with `.phar`, `.tar`, `.tar.gz`, or `.zip` files using the `PharData` class.
 *
 * @throws RuntimeException If the `PharData` class does not exist or the `phar` extension is not enabled.
 *
 * @example
 * ```php
 * use function oihana\files\phar\assertPhar;
 *
 * try
 * {
 *     assertPhar();
 *     $phar = new \PharData('/path/to/archive.tar');
 *     // proceed with Phar operations...
 * }
 * catch (\RuntimeException $e)
 * {
 *     echo "Phar support is not available: " . $e->getMessage();
 * }
 * ```
 *
 * @package oihana\files\phar
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
function assertPhar(): void
{
    if ( !class_exists('PharData' ) || !extension_loaded('phar' ) )
    {
        throw new RuntimeException( 'PharData is not available. Please ensure the phar extension is enabled.' ) ;
    }
}