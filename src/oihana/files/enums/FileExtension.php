<?php

namespace oihana\files\enums;

use oihana\enums\Char;
use oihana\reflections\traits\ConstantsTrait;

class FileExtension
{
    use ConstantsTrait
    {
        resetCaches as internalResetCaches ;
    }

    // --- Audio ---

    public const string AAC  = '.aac';
    public const string FLAC = '.flac';
    public const string M4A  = '.m4a';
    public const string MP3  = '.mp3';
    public const string OGG  = '.ogg';
    public const string WAV  = '.wav';
    public const string WMA  = '.wma';

    // --- Images ---

    public const string AI   = '.ai';
    public const string BMP  = '.bmp';
    public const string GIF  = '.gif';
    public const string ICO  = '.ico';
    public const string JPEG = '.jpeg';
    public const string JPG  = '.jpg';
    public const string PNG  = '.png';
    public const string PSD  = '.psd';
    public const string SVG  = '.svg';
    public const string TIF  = '.tif';
    public const string TIFF = '.tiff';
    public const string WEBP = '.webp';

    // --- Video ---

    public const string AVI  = '.avi';
    public const string FLV  = '.flv';
    public const string M4V  = '.m4v';
    public const string MKV  = '.mkv';
    public const string MOV  = '.mov';
    public const string MP4  = '.mp4';
    public const string MPG  = '.mpg';
    public const string MPEG = '.mpeg';
    public const string WEBM = '.webm';
    public const string WMV  = '.wmv';

    // --- Documents ---

    public const string CSV  = '.csv';
    public const string DOC  = '.doc';
    public const string DOCX = '.docx';
    public const string ODP  = '.odp';
    public const string ODS  = '.ods';
    public const string ODT  = '.odt';
    public const string PDF  = '.pdf';
    public const string PPT  = '.ppt';
    public const string PPTX = '.pptx';
    public const string RTF  = '.rtf';
    public const string TXT  = '.txt';
    public const string XLS  = '.xls';
    public const string XLSX = '.xlsx';

    // --- Archives ---

    public const string ENCRYPTED        = '.enc';
    public const string GZ               = '.gz';
    public const string RAR              = '.rar';
    public const string SEVEN_Z          = '.7z';
    public const string TAR              = '.tar';
    public const string TAR_BZ2          = '.tar.bz2';
    public const string TAR_GZ           = '.tar.gz';
    public const string TAR_GZ_ENCRYPTED = '.tar.gz.enc';
    public const string TGZ              = '.tgz' ;
    public const string ZIP              = '.zip';

    // --- Text and Code

    public const string CSS  = '.css';
    public const string HTM  = '.htm';
    public const string HTML = '.html';
    public const string JS   = '.js';
    public const string JSON = '.json';
    public const string MD   = '.md';
    public const string PHP  = '.php';
    public const string SQL  = '.sql';
    public const string TOML = '.toml';
    public const string XML  = '.xml';
    public const string YAML = '.yaml';
    public const string YML  = '.yml';

    // --- Fonts

    public const string OTF   = '.otf';
    public const string TTF   = '.ttf';
    public const string WOFF  = '.woff';
    public const string WOFF2 = '.woff2';

    // --- System
    public const string APP = '.app';
    public const string BAT = '.bat';
    public const string COM = '.com';
    public const string DLL = '.dll';
    public const string DMG = '.dmg';
    public const string EXE = '.exe';
    public const string JAR = '.jar';
    public const string SH  = '.sh';

    // --- Databases
    public const string ACCDB  = '.accdb';
    public const string DB     = '.db';
    public const string MDB    = '.mdb';
    public const string SQLITE = '.sqlite';

    // ------------------------------------ Methods

    /**
     * @var array|null
     */
    private static ?array $MIME_TYPES = null ;

    /**
     * @var array|null
     */
    private static ?array $MULTIPLE_EXTENSIONS = null ;

    /**
     * Returns the extension(s) of the specific mime-type value.
     * @param string $mimeType The mime-type to evaluates.
     * @return array|string|null
     */
    public static function getFromMimeType( string $mimeType ): array|string|null
    {
        return FileMimeType::getExtension( $mimeType ) ;
    }

    /**
     * Returns the mimetype(s) for a given extension.
     * @param string $extension
     * @return string|array|null
     */
    public static function getMimeType( string $extension ):string|array|null
    {
        if( empty( $extension ) )
        {
            return null ;
        }

        $extension = Char::DOT . strtolower(  ltrim( trim( $extension ) , Char::DOT ) );

        if( static::$MIME_TYPES === null )
        {
            static::$MIME_TYPES = FileMimeType::getAll() ;
        }

        return static::$MIME_TYPES[ self::getConstant( $extension ) ] ?? null;
    }

    /**
     * Returns the list of all multiple part extensions, ex: [ '.tar.gz.enc' , '.tar.gz' , ... ]
     * @param array|null $customs A list of custom multiple part extensions to append in the final list.
     * @return array
     */
    public static function getMultiplePartExtensions( ?array $customs = [] ):array
    {
        if( !isset( static::$MULTIPLE_EXTENSIONS ) )
        {
            static::$MULTIPLE_EXTENSIONS = [] ;
            $all = static::getAll() ;
            foreach ( $all as $extension )
            {
                $parts = explode(Char::DOT , $extension ) ;
                $count = count( $parts ) ;
                if( $count > 2 )
                {
                    static::$MULTIPLE_EXTENSIONS[] = $extension ;
                }
            }
            sort( static::$MULTIPLE_EXTENSIONS , SORT_STRING ) ;
        }

        $extensions = static::$MULTIPLE_EXTENSIONS ;

        if( is_array( $customs ) && count( $customs ) > 0 )
        {
            $extensions = array_unique( [ ...$extensions , ...$customs ] , SORT_STRING ) ;
            sort( $extensions , SORT_STRING ) ;
        }

        return $extensions ;
    }

    /**
     * Reset the internal cache of the static methods.
     * @return void
     */
    public static function resetCaches(): void
    {
        static::internalResetCaches() ;
        static::$MIME_TYPES = null ;
        static::$MULTIPLE_EXTENSIONS = null ;
    }
}