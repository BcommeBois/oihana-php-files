<?php

namespace oihana\files;

use RuntimeException;

use oihana\enums\Char;
use oihana\files\enums\FileExtension;
use oihana\files\exceptions\DirectoryException;
use oihana\files\exceptions\FileException;

/**
 * Manage creating and extracting Unix tar (optionally gz‑compressed) archives.
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
    public function assertFile( string $file , array $mimeTypes = [ 'x-tar' , 'gzip' , 'bzip2'] ): true
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
        foreach ($mimeTypes as $type)
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
     * Creates a tar file from a directory.
     * @param string $directory The directory to archive.
     * @param string $extension The extension of the new file
     * @return string The tar file.
     * @throws DirectoryException
     * @throws RuntimeException
     */
    public function create( string $directory , string $extension = FileExtension::TAR_GZ ): string
    {
        assertDirectory( $directory ) ;

        $archiveName = basename( $directory ) ;
        $tarFile     = $directory . $extension;
        $command     = "tar -czf $tarFile -C " . dirname( $directory ) . Char::SPACE . $archiveName ;

        system( $command , $status ) ;

        if ( $status == 0 )
        {
            return $tarFile;
        }
        else
        {
            throw new RuntimeException( sprintf( 'Creates a tar file failed, impossible to creates the tar file from the directory %s.' , json_encode( $directory , JSON_UNESCAPED_SLASHES ) ) , $status ) ;
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
}
