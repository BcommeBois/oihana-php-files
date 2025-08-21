<?php

namespace oihana\files\enums;

use oihana\files\enums\traits\AudioMimeTypeTrait;
use oihana\files\enums\traits\ImageMimeTypeTrait;
use oihana\files\enums\traits\VideoMimeTypeTrait;
use oihana\reflect\traits\ConstantsTrait;

/**
 * FileExtension
 *
 * Enumeration class that defines various file mime-types supported by the application.
 *
 * @package oihana\files\enums
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
class FileMimeType
{
    use AudioMimeTypeTrait ,
        ImageMimeTypeTrait ,
        VideoMimeTypeTrait ,
        ConstantsTrait
        {
            resetCaches as internalResetCaches ;
        }

    // --- Images (extras) ---

    public const array  AI  = [ 'application/postscript', 'application/illustrator' ];
    public const string PSD = 'image/vnd.adobe.photoshop';

    // --- Documents ---

    public const string CSV  = 'text/csv' ;
    public const string DOC  = 'application/msword' ;
    public const string DOCX = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ;
    public const string ODP  = 'application/vnd.oasis.opendocument.presentation' ;
    public const string ODS  = 'application/vnd.oasis.opendocument.spreadsheet' ;
    public const string ODT  = 'application/vnd.oasis.opendocument.text' ;
    public const string PDF  = 'application/pdf' ;
    public const string PPT  = 'application/vnd.ms-powerpoint' ;
    public const string PPTX = 'application/vnd.openxmlformats-officedocument.presentationml.presentation' ;
    public const string RTF  = 'application/rtf' ;
    public const string TXT  = 'text/plain' ;
    public const string XLS  = 'application/vnd.ms-excel' ;
    public const string XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' ;

    // --- Archives ---

    public const string ENCRYPTED = 'application/octet-stream' ;
    public const string GZ        = 'application/gzip' ;
    public const string RAR       = 'application/vnd.rar' ;
    public const string SEVEN_Z   = 'application/x-7z-compressed' ;
    public const string ZIP       = 'application/zip' ;

    public const array  TAR              = [ 'application/tar' , 'application/x-tar' ] ;
    public const string TAR_BZ2          = 'application/x-bzip2' ;
    public const string TAR_GZ           = 'application/gzip' ;
    public const string TAR_GZ_ENCRYPTED = 'application/octet-stream' ;
    public const string TGZ              = 'application/gzip' ;

    // --- Text and Code

    public const string CSS  = 'text/css' ;
    public const string HTM  = 'text/html' ;
    public const string HTML = 'text/html' ;
    public const array  JS   = [ 'application/javascript', 'text/javascript' ] ; 
    public const string JSON = 'application/json' ;
    public const string MD   = 'text/markdown' ;  // RFC 7763
    public const array  PHP  = [ 'application/x-php' , 'application/x-httpd-php' , 'application/x-httpd-php-source' , 'text/php' , 'text/x-php' , 'application/php' ];
    public const string TOML = 'application/toml' ;  // draft (IETF)
    public const string XML  = 'application/xml' ;
    public const array  YAML = [ 'application/x-yaml' , 'text/yaml' ] ;
    public const string YML  = 'application/x-yaml' ;

    // --- Fonts

    public const string OTF   = 'font/otf' ;
    public const string TTF   = 'font/ttf' ;
    public const string WOFF  = 'font/woff' ;
    public const string WOFF2 = 'font/woff2' ;

    // --- System
    public const string APP = 'application/octet-stream' ;
    public const string BAT = 'application/x-bat' ;
    public const string COM = 'application/x-msdownload' ;
    public const string DLL = 'application/x-msdownload' ;
    public const string DMG = 'application/x-apple-diskimage' ;
    public const array  EXE = [ 'application/x-msdownload' , 'application/vnd.microsoft.portable-executable' ] ;
    public const string JAR = 'application/java-archive' ;
    public const string SH  = 'application/x-sh' ;

    // --- Databases
    public const string ACCDB  = 'application/msaccess'     ;
    public const string DB     = 'application/octet-stream' ;
    public const string MDB    = 'application/x-msaccess'   ;
    public const string SQL    = 'application/sql' ;
    public const string SQLITE = 'application/vnd.sqlite3'  ;

    // ------------------------------------ Methods

    /**
     * @var array|null
     */
    private static ?array $EXTENSIONS = null ;

    /**
     * Returns the mimetype(s) for a given code.
     * @param string $mimeType The mime type to evaluates.
     * @return string|array|null
     */
    public static function getExtension( string $mimeType ):string|array|null
    {
        if( empty( $mimeType ) )
        {
            return null ;
        }

        if ( static::$EXTENSIONS === null )
        {
            static::$EXTENSIONS = FileExtension::getAll() ;
        }

        $constantName = self::getConstant( $mimeType ) ;

        if ( $constantName === null )
        {
            return null; // mime inconnu
        }

        if( is_array( $constantName ) )
        {
            $extensions = [] ;
            foreach ( $constantName as $name )
            {
                $extensions[] = static::$EXTENSIONS[ $name ] ;
            }
            sort( $extensions ) ;
            return $extensions ;
        }
        else
        {
            return static::$EXTENSIONS[ $constantName ] ;
        }
    }

    /**
     * Returns the extension(s) of the specific mime-type value.
     * @param string $extension The extension to evaluates.
     * @return array|string|null
     */
    public static function getFromExtension( string $extension ): array|string|null
    {
        return FileExtension::getMimeType( $extension ) ;
    }

    /**
     * Reset the internal cache of the static methods.
     * @return void
     */
    public static function resetCaches(): void
    {
        static::internalResetCaches();
        static::$EXTENSIONS = null ;
    }
}