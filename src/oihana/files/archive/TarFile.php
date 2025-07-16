<?php

namespace oihana\files\archive;

use Exception;
use oihana\enums\Char;
use oihana\files\enums\CompressionType;
use oihana\files\enums\FileExtension;
use oihana\files\exceptions\DirectoryException;
use oihana\files\exceptions\FileException;
use Phar;
use PharData;
use RuntimeException;
use function oihana\files\assertDirectory;
use function oihana\files\assertFile;
use function oihana\files\makeDirectory;

/**
 * Manages creating and extracting Unix tar (optionally gz‑compressed) archives.
 * If that component is not available you can switch to the fallback methods that
 */
final class TarFile
{
    /**
     * Supported tar‑related extensions (lower‑case, with leading dot).
     * @var string[]
     */
    public const array TAR_EXTENSIONS = [ FileExtension::GZ , FileExtension::TAR , FileExtension::TGZ , FileExtension::TAR_BZ2 , FileExtension::TAR_GZ ] ;

    /**
     * Heuristic: validate a file is a tar or gzip‑compressed tar archive.
     * @param string $file The file to evaluates.
     * @param array $mimeTypes The mimetypes to validate the file.
     * @return true True if the file is a tar file, false otherwise.
     * @throws FileException If the archive does not exist or is not a tar file.
     * @throws RuntimeException When extraction fails or directory cannot be created.
     */
    public function assertFile( string $file , array $mimeTypes = [ 'x-tar' , 'gzip' , 'bzip2' ] ): true
    {
        assertFile( $file );

        $extension = Char::DOT . strtolower( (string) pathinfo( $file, PATHINFO_EXTENSION ) ) ;

        if ( !in_array( $extension, self::TAR_EXTENSIONS ) )
        {
            throw new FileException( sprintf('File "%s" does not have a tar‑related extension.' , $file ) ) ;
        }

        $info     = finfo_open(FILEINFO_MIME_TYPE ) ;
        $mimeType = finfo_file( $info , $file ) ?: Char::EMPTY ;

        finfo_close( $info ) ;

        $mimeTypeMatches = false;
        foreach ( $mimeTypes as $type )
        {
            if ( str_contains( $mimeType , $type ) )
            {
                $mimeTypeMatches = true ;
                break;
            }
        }

        if ( !$mimeTypeMatches )
        {
            throw new FileException( sprintf('File "%s" is not recognised as a tar archive (Mime-Type: %s).' , $file , $mimeType ) ) ;
        }

        return true;
    }

    /**
     * Creates a tar archive from a directory with specified compression.
     * @param string $directory The directory to archive.
     * @param ?string $compression The compression type to use.
     * @param string|null $outputPath Optional custom output path. If null, uses directory name with appropriate extension.
     * @return string The path to the created archive.
     * @throws DirectoryException If the directory does not exist.
     * @throws RuntimeException If the archive creation fails.
     */
    public function createFromDirectory( string $directory , ?string $compression = CompressionType::GZIP , ?string $outputPath = null ): string
    {
        assertDirectory( $directory ) ;

        $archiveName = basename( $directory ) ;

        if ( $outputPath === null )
        {
            $extension  = $this->getExtensionForCompression( $compression ) ;
            $outputPath = $directory . $extension ;
        }

        try
        {
            $baseTarPath = $compression === CompressionType::NONE
                         ? $outputPath
                         : sys_get_temp_dir() . DIRECTORY_SEPARATOR . $archiveName . FileExtension::TAR ;

            $phar = new PharData( $baseTarPath ) ;
            $phar->buildFromDirectory( $directory ) ;

            // Apply compression if requested
            if ( $compression !== CompressionType::NONE )
            {
                $compressedPhar = $phar->compress( $this->getPharCompressionType( $compression ) ) ;

                unset( $phar ) ;

                $compressedPath = $baseTarPath . $this->getCompressionExtension( $compression ) ;
                if ( $compressedPath !== $outputPath )
                {
                    rename( $compressedPath , $outputPath ) ;
                }

                if ( file_exists( $baseTarPath ) )
                {
                    unlink( $baseTarPath ) ;
                }
            }
            else
            {
                unset( $phar ) ;
            }

            return $outputPath;

        }
        catch ( Exception $e )
        {
            throw new RuntimeException( sprintf( 'Failed to create tar archive from directory %s. Error: %s' , $directory , $e->getMessage() ) ) ;
        }
    }

    /**
     * Extracts a tar file into a directory.
     * @param string $tarFile The tar file to extract.
     * @param string $directory The directory to extract to.
     * @return bool true
     * @throws FileException
     * @throws RuntimeException
     * @throws DirectoryException
     */
    public function extract( string $tarFile , string $directory ): true
    {
        $this->assertFile( $tarFile ) ;

        makeDirectory( $directory ) ;

        $command = "tar -xzf $tarFile -C $directory" ;

        system( $command , $status ) ;

        if ( $status == 0 )
        {
            return true ;
        }
        else
        {
            throw new RuntimeException
            (
                sprintf
                (
                    'Extract tar file failed, impossible to extract the tar file %s to directory %s.' ,
                    json_encode( $tarFile   , JSON_UNESCAPED_SLASHES ) ,
                    json_encode( $directory , JSON_UNESCAPED_SLASHES )
                )
                , $status
            ) ;
        }
    }

    /**
     * Vérifie si PharData est disponible.
     * @return bool True si PharData est disponible, false sinon.
     */
    public static function isPharAvailable(): bool
    {
        return class_exists('PharData' ) && extension_loaded('phar' ) ;
    }

    /**
     * Gets the appropriate file extension for a compression type.
     * @param string $compression The compression type.
     * @return string The file extension.
     */
    private function getExtensionForCompression( string $compression ): string
    {
        return match ( $compression )
        {
            CompressionType::GZIP  => FileExtension::TAR_GZ   ,
            CompressionType::BZIP2 => FileExtension::TAR_BZ2  ,
            default                => FileExtension::TAR      , // NONE
        } ;
    }

    /**
     * Gets the Phar compression constant for a compression type.
     * @param string $compression The compression type.
     * @return int The Phar compression constant.
     */
    private function getPharCompressionType( string $compression ): int
    {
        return match ( $compression )
        {
            CompressionType::GZIP  => Phar::GZ ,
            CompressionType::BZIP2 => Phar::BZ2 ,
            CompressionType::NONE  => throw new RuntimeException('No compression type specified' ) ,
        } ;
    }

    /**
     * Gets the file extension added by Phar compression.
     * @param string $compression The compression type.
     * @return string The extension added by compression.
     */
    private function getCompressionExtension( string $compression ): string
    {
        return match ( $compression )
        {
            CompressionType::GZIP  => FileExtension::GZ  ,
            CompressionType::BZIP2 => FileExtension::BZ2 ,
            CompressionType::NONE  => Char::EMPTY ,
        } ;
    }
}
