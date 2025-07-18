<?php

namespace oihana\files\phar ;

use Phar;

use oihana\files\enums\CompressionType;
use oihana\files\exceptions\UnsupportedCompressionException;

/**
 * Returns the corresponding Phar compression constant for a given compression type.
 *
 * This function maps a compression type (e.g., `gzip`, `bzip2`, or `none`) to
 * the appropriate Phar compression constant (e.g., `Phar::GZ`, `Phar::BZ2`, `Phar::NONE`).
 *
 * It is useful when setting or detecting compression in Phar archives programmatically.
 *
 * @param string $compression The compression type to resolve.
 *                            Must be one of the values defined in {@see CompressionType}.
 *
 * @return int The Phar compression constant (Phar::GZ, Phar::BZ2, or Phar::NONE).
 *
 * @throws UnsupportedCompressionException If the given compression type is not supported.
 *
 * @package oihana\files\phar
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 *
 * @example
 * ```php
 * use oihana\files\phar\getPharCompressionType;
 * use oihana\files\enums\CompressionType;
 *
 * $compression = CompressionType::GZIP;
 * $pharConstant = getPharCompressionType($compression);
 *
 * echo $pharConstant; // Outputs: 4096 (Phar::GZ)
 * ```
 */
function getPharCompressionType( string $compression ): int
{
    return match ( $compression )
    {
        CompressionType::GZIP  => Phar::GZ ,
        CompressionType::BZIP2 => Phar::BZ2 ,
        CompressionType::NONE  => Phar::NONE ,
        default                => throw new UnsupportedCompressionException( sprintf( "Compression type '%s' is not supported", $compression ) )
    } ;
}