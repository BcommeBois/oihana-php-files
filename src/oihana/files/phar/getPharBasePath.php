<?php

namespace oihana\files\phar ;

use PharData;

/**
 * Returns the base path of an archive, formatted for the `phar://` stream wrapper.
 *
 * This function prefixes the archive's system path with `phar://` to create a
 * base URI. This URI is essential for manipulating the paths of files contained
 * within the archive, for instance, when using `str_replace` to find a relative path.
 *
 * @param PharData $phar The archive's PharData instance.
 *
 * @return string The base URI for the `phar://` stream (e.g., `phar:///path/to/archive.tar`).
 */
function getPharBasePath( PharData $phar ):string
{
    return 'phar://' . realpath( $phar->getPath() ) ;
}