<?php

namespace oihana\files\enums;

use oihana\enums\Char;
use oihana\files\exceptions\UnsupportedCompressionException;
use oihana\reflections\traits\ConstantsTrait;

/**
 * FileExtension
 *
 * Enumeration class that defines various file extension supported by the application.
 *
 * @package oihana\files\enums
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
class TarExtension
{
    use ConstantsTrait ;

    public const string TAR      = '.tar' ;
    public const string TAR_GZ   = '.tar.gz' ;
    public const string TAR_BZ2  = '.tar.bz2' ;
    public const string TAR_XZ   = '.tar.xz' ;
    public const string TAR_LZ   = '.tar.lz' ;
    public const string TAR_LZO  = '.tar.lzo' ;
    public const string TAR_LZMA = '.tar.lzma' ;
    public const string TAR_ZST  = '.tar.zst' ;
    public const string TAR_Z    = '.tar.Z' ;
    public const string TBZ2     = '.tbz2' ;
    public const string TXZ      = '.txz' ;
    public const string TGZ      = '.tgz' ;

    /**
     * Gets the appropriate file extension for a compression type.
     * @param string $compression The compression type.
     * @return string The file extension.
     * @throws UnsupportedCompressionException
     */
    public static function getExtensionForCompression( string $compression ): string
    {
        return match ( $compression )
        {
            CompressionType::GZIP  => FileExtension::TAR_GZ   ,
            CompressionType::BZIP2 => FileExtension::TAR_BZ2  ,
            CompressionType::NONE  => FileExtension::TAR      ,
            default                => throw new UnsupportedCompressionException( sprintf( "Compression type '%s' is not supported", $compression ) )
        } ;
    }

    /**
     * Gets the file extension added by Phar compression.
     * @param string $compression The compression type.
     * @return string The extension added by compression.
     * @throws UnsupportedCompressionException
     */
    public static function getCompressionExtension( string $compression ): string
    {
        return match ( $compression )
        {
            CompressionType::GZIP  => FileExtension::GZ  ,
            CompressionType::BZIP2 => FileExtension::BZ2 ,
            CompressionType::NONE  => Char::EMPTY ,
            default                => throw new UnsupportedCompressionException( sprintf( "Compression type '%s' is not supported", $compression ) )
        } ;
    }
}