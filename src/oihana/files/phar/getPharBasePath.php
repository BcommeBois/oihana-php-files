<?php

namespace oihana\files\phar ;

use PharData;

/**
 * Returns the base path of an archive, formatted for the `phar://` stream wrapper.
 *
 * This function constructs a base URI by prefixing the archive's real path with `phar://`.
 * This URI can be used to access or manipulate the internal files of the archive via PHP's stream wrapper.
 *
 * It is particularly useful when extracting or analyzing archive contents by building full
 * paths to the internal files (e.g., `phar:///path/to/archive.tar/dir/file.txt`).
 *
 * @param PharData $phar The archive's `PharData` instance.
 *
 * @return string The base `phar://` URI to access the contents of the archive
 *                (e.g., `phar:///absolute/path/to/archive.tar`).
 *
 * @throws \RuntimeException If the archive file does not exist or is not readable.
 *
 * @package oihana\files\phar
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 *
 * @example
 * ```php
 * use oihana\files\phar\assertPhar;
 * use oihana\files\phar\getPharBaseUri;
 *
 * assertPhar();
 *
 * $phar = new \PharData('/absolute/path/to/archive.tar');
 * $baseUri = getPharBaseUri($phar);
 *
 * echo $baseUri; // Outputs: phar:///absolute/path/to/archive.tar
 *
 * // Access an internal file:
 * $content = file_get_contents($baseUri . '/docs/readme.txt');
 * ```
 */
function getPharBasePath( PharData $phar ):string
{
    return 'phar://' . realpath( $phar->getPath() ) ;
}