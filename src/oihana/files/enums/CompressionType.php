<?php

namespace oihana\files\enums;

use oihana\reflections\traits\ConstantsTrait;

/**
 * CompressionType
 *
 * Enumeration class that defines various compression types supported by the application.
 * This class provides constants for different compression algorithms that can be used
 * for file compression and decompression operations.
 *
 * @package oihana\files\enums
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
class CompressionType
{
    use ConstantsTrait ;

    /**
     * No compression applied
     *
     * Represents the absence of compression. Files using this type
     * are stored in their original, uncompressed format.
     *
     * @var string
     */
    public const string NONE  = 'none';

    /**
     * GNU zip compression
     *
     * GZIP compression algorithm based on DEFLATE. Provides good compression
     * ratios with reasonable speed. Commonly used for web content and
     * single file compression.
     *
     * @var string
     */
    public const string GZIP = 'gzip';

    /**
     * Bzip2 compression
     *
     * Block-sorting compression algorithm that typically achieves better
     * compression ratios than GZIP but with slower compression/decompression
     * speed. Good for archival purposes.
     *
     * @var string
     */
    public const string BZIP2 = 'bzip2';

    /**
     * ZIP compression
     *
     * Popular archive format that supports multiple files and directories.
     * Uses DEFLATE compression algorithm and is widely supported across
     * different platforms and applications.
     *
     * @var string
     */
    public const string ZIP = 'zip';

    /**
     * LZ4 compression
     *
     * Fast compression algorithm that prioritizes speed over compression ratio.
     * Excellent for real-time applications where quick compression/decompression
     * is more important than file size reduction.
     *
     * @var string
     */
    public const string LZ4 = 'lz4';

    /**
     * LZMA compression
     *
     * Lempel-Ziv-Markov chain algorithm that provides excellent compression
     * ratios at the cost of higher CPU usage and memory consumption.
     * Used in 7-Zip and XZ formats.
     *
     * @var string
     */
    public const string LZMA = 'lzma';

    /**
     * XZ compression
     *
     * Container format that typically uses LZMA2 compression algorithm.
     * Provides very good compression ratios and is commonly used in
     * Unix-like systems for software distribution.
     *
     * @var string
     */
    public const string XZ = 'xz';

    /**
     * ZStandard compression
     *
     * Modern compression algorithm developed by Facebook that offers
     * a good balance between compression ratio and speed. Suitable
     * for real-time compression scenarios.
     *
     * @var string
     */
    public const string ZSTD = 'zstd';

    /**
     * Get the default compression type
     *
     * Returns the recommended default compression type for general use.
     * GZIP is chosen as it provides a good balance between compression
     * ratio and performance.
     *
     * @return string The default compression type
     */
    public static function getDefault(): string
    {
        return self::GZIP;
    }

    /**
     * Get compression types that support fast compression
     *
     * Returns an array of compression types that are optimized for speed
     * rather than compression ratio. Useful for real-time applications.
     *
     * @return array<string> Array of fast compression types
     */
    public static function getFastCompressionTypes(): array
    {
        return [ self::NONE, self::LZ4, self::ZSTD ] ;
    }

    /**
     * Get compression types that provide high compression ratios
     *
     * Returns an array of compression types that prioritize compression
     * ratio over speed. Suitable for archival and storage optimization.
     *
     * @return array<string> Array of high-ratio compression types
     */
    public static function getHighRatioCompressionTypes(): array
    {
        return [ self::LZMA , self::XZ , self::BZIP2 ] ;
    }
}