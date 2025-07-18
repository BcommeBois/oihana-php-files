<?php

namespace oihana\files\archive\tar;

/**
 * Checks if a given tar file is compressed.
 *
 * This function determines whether the file name indicates a compressed tar archive
 * based on common compressed tar extensions such as `.tar.gz`, `.tgz`, `.tar.bz2`, or `.tbz2`.
 *
 * Note: This function only inspects the file name extension, not the actual file contents.
 *
 * @param string $tarFile The path or filename of the tar archive.
 *
 * @return bool True if the file is recognized as a compressed tar archive, false otherwise.
 *
 * @example
 * ```php
 * var_dump( tarIsCompressed( 'archive.tar.gz'   ) ); // true
 * var_dump( tarIsCompressed( 'archive.tgz'      ) ); // true
 * var_dump( tarIsCompressed( 'archive.tar.bz2'  ) ); // true
 * var_dump( tarIsCompressed( 'archive.tbz2'     ) ); // true
 * var_dump( tarIsCompressed( 'archive.tar'      ) ); // false
 * var_dump( tarIsCompressed( 'archive.zip'      ) ); // false
 * var_dump( tarIsCompressed( 'README.md'        ) ); // false
 * ```
 *
 * @package oihana\files\archive\tar
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
function tarIsCompressed( string $tarFile ) :bool
{
    return preg_match('/\.tar\.(gz|bz2)|\.tgz|\.tbz2$/i', $tarFile) === 1;
}
