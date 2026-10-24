<?php

namespace oihana\files\archive\tar ;

use oihana\files\enums\TarExtension;
use oihana\files\exceptions\UnsupportedCompressionException;

/**
 * Gives a compressed archive a second, uniquely named path, so decompressing it twice in
 * one process does not collide.
 *
 * **The collision.** `PharData::decompress()` writes its `.tar` next to the archive,
 * named after it, and registers it in a list of open phars that removing the file does
 * not clear. Extracting the same `.tar.gz` a second time therefore failed with *"Unable
 * to add newly converted phar ... a phar with that name already exists"* — measured on
 * 2026-10-24, and a caller restoring several sites in one run meets it on the second.
 *
 * `Phar::unlinkArchive()` is the documented way to clear both, and calling it from
 * {@see untar()} **corrupted the Zend heap**. So the collision is avoided rather than
 * cleaned up: give the archive a unique name and the `.tar` inherits it.
 *
 * A **hard link** first, which costs no bytes and no time whatever the archive weighs;
 * `copy()` only if the filesystem refuses one. Beside the archive in both cases, because
 * a hard link cannot cross a filesystem — and because that is already where
 * `decompress()` writes.
 *
 * @param string $tarFile     Path to the compressed archive.
 * @param string $compression {@see \oihana\files\enums\CompressionType::GZIP} or `BZIP2`,
 *                            as reported by {@see tarCompressionOfFile()}. The alias ends
 *                            in the matching extension, so `decompress()` strips it the
 *                            same way.
 *
 * @return string|null The alias, to be removed by the caller, or `null` when neither a
 *                     link nor a copy could be made — in which case the caller is
 *                     expected to carry on with the archive itself rather than fail an
 *                     extraction that would otherwise work.
 *
 * @throws UnsupportedCompressionException If the compression has no known extension.
 *
 * @example
 * ```php
 * use oihana\files\enums\CompressionType;
 * use function oihana\files\archive\tar\untarUniqueAlias;
 *
 * $alias = untarUniqueAlias( '/backups/site.tar.gz' , CompressionType::GZIP ) ;
 * // → '/backups/untar-6710f3c8d1e29.tar.gz', the same inode
 * ```
 *
 * @package oihana\files\archive\tar
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.5.0
 */
function untarUniqueAlias( string $tarFile , string $compression ) :?string
{
    $alias = dirname( $tarFile )
           . DIRECTORY_SEPARATOR
           . uniqid( 'untar-' , true )
           . TarExtension::getExtensionForCompression( $compression ) ;

    if ( @link( $tarFile , $alias ) )
    {
        return $alias ;
    }

    // A filesystem that refuses hard links, or a cross-device path: pay for the copy
    // rather than give up the guarantee.
    // @codeCoverageIgnoreStart
    if ( @copy( $tarFile , $alias ) )
    {
        return $alias ;
    }

    return null ;
    // @codeCoverageIgnoreEnd
}
