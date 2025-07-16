<?php

namespace oihana\files ;

use oihana\files\enums\CompressionType;
use Phar;
use RuntimeException;

/**
 * Gets the Phar compression constant for a compression type.
 * @param string $compression The compression type.
 * @return int The Phar compression constant.
 * @throw RuntimeException If the given compression is not supported by Phar.
 */
function getPharCompressionType( string $compression ): int
{
    return match ( $compression )
    {
        CompressionType::GZIP  => Phar::GZ ,
        CompressionType::BZIP2 => Phar::BZ2 ,
        CompressionType::NONE  => Phar::NONE ,
        default                => throw new RuntimeException( sprintf( "Compression type '%s' is not supported", $compression ) )
    } ;
}