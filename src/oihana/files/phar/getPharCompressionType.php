<?php

namespace oihana\files ;

use oihana\files\enums\CompressionType;
use oihana\files\exceptions\UnsupportedCompressionException;
use Phar;

/**
 * Gets the Phar compression constant for a compression type.
 * @param string $compression The compression type.
 * @return int The Phar compression constant.
 * @throw RuntimeException If the given compression is not supported by Phar.
 * @throws UnsupportedCompressionException
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