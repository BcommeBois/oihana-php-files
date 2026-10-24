<?php

namespace oihana\files\archive\tar ;

use Phar;

use oihana\files\enums\CompressionType;

/**
 * The compressions {@see tar()} can actually write **on this host**, in a stable order.
 *
 * Not a constant list, because it is not a property of this library: it is a property of
 * the machine. Two engines contribute, and they do not cover the same formats.
 *
 * - **PharData** compresses through PHP extensions, so it offers `gzip` with `ext-zlib`
 *   and `bzip2` with `ext-bz2`, and nothing else — `Phar` knows no other.
 * - **The system `tar`** pipes through a program, so it offers whatever it can find:
 *   `gzip`, `bzip2`, `xz` and `zstd`. Which means a host can write `.tar.zst` and the one
 *   next to it cannot, with the same code and the same version of this library.
 *
 * `none` is always there.
 *
 * **Why this is a function and not documentation.** `tar()` used to refuse `xz` with
 * *"Compression type 'xz' is not supported"*, raised by a helper assembling a temporary
 * path, seventeen lines before the engines had even been looked at — a sentence about
 * this library where the truth was about the host. The refusal now names what is missing
 * and what would work here, and this is where it reads it. It is also what a `doctor`
 * command should print: a host that silently writes `.tar.gz` where the operator
 * expected `.tar.zst` has nothing else to tell them.
 *
 * Deliberately absent: **lzma**, which `xz` superseded and whose `--lzma` flag
 * {@see tarCompressionFlag()} still carries for a caller going straight to
 * {@see tarWithBinary()}, and **lz4**, for which GNU tar has no flag at all.
 *
 * @return array<int,string> Values of {@see CompressionType}, `none` first.
 *
 * @example
 * ```php
 * use function oihana\files\archive\tar\tarWritableCompressions;
 *
 * tarWritableCompressions() ;
 * // → ['none','gzip','bzip2','xz','zstd'] on a Debian with xz-utils and zstd
 * // → ['none','gzip','bzip2']             on a host with no GNU tar
 * ```
 *
 * @package oihana\files\archive\tar
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.5.0
 */
function tarWritableCompressions() :array
{
    $writable = [ CompressionType::NONE ] ;

    if ( Phar::canCompress( Phar::GZ ) )
    {
        $writable[] = CompressionType::GZIP ;
    }

    if ( Phar::canCompress( Phar::BZ2 ) )
    {
        $writable[] = CompressionType::BZIP2 ;
    }

    if ( tarBinary() !== null )
    {
        foreach ( [ CompressionType::GZIP , CompressionType::BZIP2 , CompressionType::XZ , CompressionType::ZSTD ] as $compression )
        {
            if ( tarCompressorExists( $compression ) )
            {
                $writable[] = $compression ;
            }
        }
    }

    return array_values( array_unique( $writable ) ) ;
}
