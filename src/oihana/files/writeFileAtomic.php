<?php

namespace oihana\files ;

use oihana\files\exceptions\DirectoryException;
use oihana\files\exceptions\FileException;

/**
 * Writes content to a file **atomically**.
 *
 * The content is first written to a temporary file located in the **same
 * directory** as the target (so the final `rename()` stays on the same
 * filesystem and is therefore atomic), then renamed over the destination. A
 * concurrent reader always sees either the previous file or the fully-written
 * new one — never a half-written file. This addresses the non-atomicity caveat
 * of plain copy/write helpers (see the [copying guide](../../../wiki/en/files/copying.md)).
 *
 * The destination's parent directory is created on demand. On failure, the
 * temporary file is removed and a typed exception is thrown.
 *
 * @param string $file        Destination file path.
 * @param string $content     The content to write.
 * @param int    $permissions File permissions to set (octal, default: `0644`).
 *
 * @return string The destination file path.
 *
 * @throws FileException      If the temporary file cannot be written, permissions cannot be set,
 *                            or the atomic rename fails.
 * @throws DirectoryException If the destination directory cannot be created.
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.2.0
 *
 * @example
 * ```php
 * use function oihana\files\writeFileAtomic;
 *
 * writeFileAtomic( '/etc/myapp/config.json' , $json ) ;
 * // readers never observe a truncated config.json
 * ```
 */
function writeFileAtomic( string $file , string $content , int $permissions = 0644 ): string
{
    $directory = dirname( $file ) ;

    if ( !is_dir( $directory ) )
    {
        makeDirectory( $directory ) ;
    }

    $temporary = $directory . DIRECTORY_SEPARATOR . '.' . basename( $file ) . '.' . uniqid( 'tmp' , true ) ;

    if ( @file_put_contents( $temporary , $content , LOCK_EX ) === false )
    {
        throw new FileException( sprintf( 'Failed to write the temporary file for "%s".' , $file ) ) ;
    }

    if ( !@chmod( $temporary , $permissions ) )
    {
        // chmod on a freshly written, owned file does not fail under the test runner.
        // @codeCoverageIgnoreStart
        @unlink( $temporary ) ;
        throw new FileException( sprintf( 'Failed to set permissions %o on "%s".' , $permissions , $file ) ) ;
        // @codeCoverageIgnoreEnd
    }

    if ( !@rename( $temporary , $file ) )
    {
        // rename() within the same directory does not fail under the test runner.
        // @codeCoverageIgnoreStart
        @unlink( $temporary ) ;
        throw new FileException( sprintf( 'Failed to atomically write "%s".' , $file ) ) ;
        // @codeCoverageIgnoreEnd
    }

    return $file ;
}
