<?php

namespace oihana\files\enums;

use oihana\reflect\traits\ConstantsTrait;

/**
 * Enumeration of the keys returned by {@see \oihana\files\archive\zip\zipFileInfo()}.
 *
 * @package oihana\files\enums
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.2.0
 */
class ZipInfo
{
    use ConstantsTrait ;

    public const string COMPRESSION = 'compression' ;
    public const string EXTENSION   = 'extension'   ;
    public const string FILE_COUNT  = 'fileCount'   ;
    public const string IS_VALID    = 'isValid'     ;
    public const string TOTAL_SIZE  = 'totalSize'   ;
    public const string MIME_TYPE   = 'mimeType'    ;
}
