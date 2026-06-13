<?php

namespace oihana\files\enums;

use oihana\reflect\traits\ConstantsTrait;

/**
 * FileSizeUnit
 *
 * Enumeration of the binary (base-1024) file-size unit symbols used to render
 * human-readable sizes, from bytes up to petabytes.
 *
 * @package oihana\files\enums
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.2.0
 *
 * @see \oihana\files\formatFileSize()
 */
class FileSizeUnit
{
    use ConstantsTrait ;

    /**
     * Bytes.
     * @var string
     */
    public const string B = 'B';

    /**
     * Kilobytes (1024 bytes).
     * @var string
     */
    public const string KB = 'KB';

    /**
     * Megabytes (1024 KB).
     * @var string
     */
    public const string MB = 'MB';

    /**
     * Gigabytes (1024 MB).
     * @var string
     */
    public const string GB = 'GB';

    /**
     * Terabytes (1024 GB).
     * @var string
     */
    public const string TB = 'TB';

    /**
     * Petabytes (1024 TB).
     * @var string
     */
    public const string PB = 'PB';

    /**
     * Returns the unit symbols ordered by ascending magnitude (base 1024).
     *
     * The index of each symbol is the power of 1024 it represents
     * (`0 => B`, `1 => KB`, … `5 => PB`).
     *
     * @return array<int,string> The ordered list of unit symbols.
     */
    public static function ordered(): array
    {
        return [ self::B , self::KB , self::MB , self::GB , self::TB , self::PB ] ;
    }
}
