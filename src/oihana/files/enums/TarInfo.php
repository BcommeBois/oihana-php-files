<?php

namespace oihana\files\enums;

use oihana\reflections\traits\ConstantsTrait;

/**
 * FileExtension
 *
 * Enumeration class that defines various mode options to use in the findFiles function.
 *
 * @package oihana\files\enums
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
class TarInfo
{
    use ConstantsTrait ;


    public const string COMPRESSION = 'compression' ;
    public const string EXTENSION   = 'extension'   ;
    public const string FILE_COUNT  = 'fileCount'   ;
    public const string IS_VALID    = 'isValid'     ;
    public const string TOTAL_SIZE  = 'totalSize'   ;
    public const string MIME_TYPE   = 'mimeType'    ;
}