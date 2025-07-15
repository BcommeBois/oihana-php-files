<?php

namespace oihana\files\enums;

use oihana\reflections\traits\ConstantsTrait;

class FileMimeType
{
    use ConstantsTrait
    {
        resetCaches as internalResetCaches ;
    }

    // --- Audio ---

    public const string AAC  = 'audio/aac' ;
    public const string FLAC = 'audio/flac' ;
    public const array  M4A  = [ 'audio/mp4', 'audio/x-m4a' ] ;
    public const string MP3  = 'audio/mpeg' ;
    public const string OGG  = 'audio/ogg' ;
    public const string WAV  = 'audio/wav' ;
    public const string WMA  = 'audio/x-ms-wma' ;

    // --- Images ---

    public const array  AI   = [ 'application/postscript', 'application/illustrator' ];
    public const string BMP  = 'image/bmp' ;
    public const string GIF  = 'image/gif' ;
    public const string ICO  = 'image/vnd.microsoft.icon' ;
    public const string JPEG = 'image/jpeg' ;
    public const string JPG  = 'image/jpeg' ;
    public const string PNG  = 'image/png' ;
    public const string PSD  = 'image/vnd.adobe.photoshop' ;
    public const string SVG  = 'image/svg+xml' ;
    public const string TIF  = 'image/tiff' ;
    public const string TIFF = 'image/tiff' ;
    public const string WEBP = 'image/webp' ;

    // --- Video ---

    public const string AVI  = 'video/x-msvideo' ;
    public const string FLV  = 'video/x-flv' ;
    public const array  M4V  = [ 'video/x-m4' , 'video/mp4v' ];
    public const string MKV  = 'video/x-matroska' ;
    public const string MOV  = 'video/quicktime' ;
    public const string MP4  = 'video/mp4' ;
    public const string MPG  = 'video/mpeg' ;
    public const string MPEG = 'video/mpeg' ;
    public const string WEBM = 'video/webm' ;
    public const string WMV  = 'video/x-ms-wmv' ;

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

    public const string ENCRYPTED        = 'application/octet-stream' ;
    public const string GZ               = 'application/gzip' ;
    public const string RAR              = 'application/vnd.rar' ;
    public const string SEVEN_Z          = 'application/x-7z-compressed' ;
    public const string TAR              = 'application/x-tar' ;
    public const string TAR_BZ2          = 'application/x-bzip2' ;
    public const string TAR_GZ           = 'application/gzip' ;
    public const string TAR_GZ_ENCRYPTED = 'application/octet-stream' ;
    public const string TGZ              = 'application/gzip' ;
    public const string ZIP              = 'application/zip' ;

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