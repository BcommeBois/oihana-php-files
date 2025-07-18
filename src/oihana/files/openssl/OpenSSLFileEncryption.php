<?php

namespace oihana\files\openssl;

use Exception;
use InvalidArgumentException;
use oihana\enums\Char;
use RuntimeException;

use oihana\files\enums\FileExtension;
use oihana\files\exceptions\DirectoryException;
use oihana\files\exceptions\FileException;

use function oihana\files\assertDirectory;
use function oihana\files\assertFile;

/**
 * Class OpenSSLFileEncryption
 *
 * This class provides functionality to encrypt and decrypt files using OpenSSL.
 * It prepends the IV (Initialization Vector) to the encrypted data so that decryption is self-contained.
 *
 * @example
 * ```php
 * use oihana\files\openssl\OpenSSLFileEncryption;
 *
 * $crypto = new OpenSSLFileEncryption('my-secret-passphrase');
 * $encryptedPath = $crypto->encrypt('/path/to/file.txt');
 * $decryptedPath = $crypto->decrypt($encryptedPath);
 * ```
 *
 * @package oihana\files\openssl
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
class OpenSSLFileEncryption
{
    /**
     * Constructor.
     *
     * @param string $passphrase Secret key for encryption/decryption.
     * @param string $cipher     OpenSSL cipher algorithm. Default is 'aes-256-cbc'.
     *
     * @throws InvalidArgumentException If the passphrase is empty or the cipher is unsupported.
     *
     * @example
     * ```php
     * $crypto = new OpenSSLFileEncryption('my-passphrase', 'aes-256-cbc');
     * ```
     */
    public function __construct( string $passphrase, string $cipher = 'aes-256-cbc' )
    {
        if ( !in_array( $cipher , openssl_get_cipher_methods(true ) ) )
        {
            throw new InvalidArgumentException("Cipher method '$cipher' is not available");
        }

        if ( empty( $passphrase ) )
        {
            throw new InvalidArgumentException("Passphrase cannot be empty") ;
        }

        $this->cipher     = $cipher;
        $this->passphrase = $passphrase;
        $this->ivLength   = openssl_cipher_iv_length( $cipher ) ;
    }

    /**
     * Destructor: clears the passphrase from memory.
     */
    public function __destruct()
    {
        $this->passphrase = str_repeat("\0" , strlen( $this->passphrase ) ) ;
    }

    /**
     * @var string The cipher method used for encryption and decryption.
     */
    private string $cipher;

    /**
     * @var string The passphrase used for encryption and decryption.
     */
    private string $passphrase;

    /**
     * @var int The length of the initialization vector (IV) used for encryption and decryption.
     */
    public int $ivLength
    {
        get
        {
            return $this->ivLength;
        }
    }

    /**
     * Encrypts a file using OpenSSL.
     *
     * Reads the input file, generates a secure IV, encrypts the content using OpenSSL,
     * prepends the IV to the encrypted data, and writes it to the output file.
     *
     * @param string      $inputFile  Path to the file to encrypt.
     * @param string|null $outputFile Optional output file path. If null, appends `.enc`.
     *
     * @return string Path to the encrypted file.
     *
     * @throws RuntimeException     On read/write/encryption failure.
     * @throws FileException        If input file is not valid.
     * @throws DirectoryException   If output directory is not writable.
     *
     * @example
     * ```php
     * $crypto = new OpenSSLFileEncryption('secret');
     * $encryptedPath = $crypto->encrypt('/path/to/plain.txt');
     * ```
     */
    public function encrypt( string $inputFile , ?string $outputFile = null ) :string
    {
        assertFile( $inputFile ) ;

        if ( $outputFile === null )
        {
            $outputFile = $inputFile . FileExtension::ENCRYPTED ;
        }

        $data = file_get_contents( $inputFile ) ;
        if ( $data === false )
        {
            throw new RuntimeException( 'Encryption failed, unable to read the file.' ) ;
        }

        // Generate secure IV
        $iv = openssl_random_pseudo_bytes( $this->ivLength ,  $cryptoStrong ) ;
        if ( !$cryptoStrong )
        {
            throw new RuntimeException("Encryption failed: could not generate secure IV");
        }

        // Encrypt data
        $encrypted = openssl_encrypt( $data, $this->cipher, $this->passphrase, OPENSSL_RAW_DATA, $iv );
        if ( $encrypted === false )
        {
            throw new RuntimeException("Failed to encrypt the file'." ) ;
        }

        $outputDir = dirname( $outputFile ) ;

        assertDirectory ( $outputDir  , isWritable : true ) ;
        if ( file_exists( $outputFile ) && !is_writable( $outputFile ) )
        {
            throw new RuntimeException("Encryption failed, output file is not writable.");
        }

        // Write encrypted data
        $bytesWritten = @file_put_contents( $outputFile , $iv . $encrypted ) ;
        if( $bytesWritten === false )
        {
            $error = error_get_last() ;
            throw new RuntimeException( "Encryption failed, file write failed: " . ( $error ? $error['message'] : 'unknown error' ) );
        }

        return $outputFile;
    }

    /**
     * Decrypts a previously encrypted file.
     *
     * Extracts the IV from the start of the file, decrypts the remaining data,
     * and writes the decrypted content to the output file.
     *
     * @param string      $inputFile  Path to the encrypted file.
     * @param string|null $outputFile Optional output path. If null, `.enc` is stripped.
     *
     * @return string Path to the decrypted file.
     *
     * @throws RuntimeException  On read/write/decryption failure.
     * @throws FileException     If the input file is not valid.
     *
     * @example
     * ```php
     * $crypto = new OpenSSLFileEncryption('secret');
     * $decryptedPath = $crypto->decrypt('/path/to/file.txt.enc');
     * ```
     */
    public function decrypt( string $inputFile , ?string $outputFile = null ) :string
    {
        assertFile( $inputFile ) ;

        if ( $outputFile === null )
        {
            $outputFile = str_replace( FileExtension::ENCRYPTED , Char::EMPTY , $inputFile ) ;
        }

        $data = file_get_contents( $inputFile ) ;
        if ( $data === false )
        {
            throw new RuntimeException("Failed to decrypt, unable to read the file." );
        }

        // Check file contains at least IV
        if ( strlen( $data ) < $this->ivLength )
        {
            throw new RuntimeException("Failed to decrypt, file is too short to contain IV and encrypted data." ) ;
        }

        // Extract IV and encrypted data
        $iv = substr( $data , 0 , $this->ivLength ) ;
        $encryptedData = substr( $data , $this->ivLength ) ;

        // Decrypt data
        $decrypted = openssl_decrypt( $encryptedData , $this->cipher , $this->passphrase , OPENSSL_RAW_DATA , $iv ) ;
        if ($decrypted === false)
        {
            throw new RuntimeException("Decryption failed due to incorrect passphrase or corrupted data.");
        }

        // Prepare output
        $outputDir = dirname( $outputFile ) ;
        if ( !is_dir( $outputDir ) )
        {
            throw new RuntimeException("Decryption failed, output directory does not exist.");
        }

        if ( !is_writable( $outputDir ) )
        {
            throw new RuntimeException("Decryption failed, output directory is not writable." ) ;
        }

        if ( file_exists( $outputFile ) && !is_writable( $outputFile ) )
        {
            throw new RuntimeException("Decryption failed, output file is not writable.") ;
        }

        // Write decrypted data
        $bytesWritten = @file_put_contents( $outputFile , $decrypted ) ;
        if ( $bytesWritten === false )
        {
            $error = error_get_last() ;
            throw new RuntimeException
            (
                "Decryption failed, file write failed: " .
                ( $error ? $error['message'] : 'unknown error' )
            );
        }

        return $outputFile;
    }

    /**
     * Checks if a file is large enough to be encrypted.
     *
     * Verifies that the file has at least enough bytes to contain an IV.
     * This does not confirm it was encrypted or can be decrypted.
     *
     * @param string $filePath Path to the file to check.
     *
     * @return bool True if the file is at least as long as the IV size.
     *
     * @example
     * ```php
     * if ( $crypto->hasEncryptedFileSize('/file') )
     * {
     *     echo "Looks like an encrypted file (size).";
     * }
     * ```
     */
    public function hasEncryptedFileSize( string $filePath ): bool
    {
        if ( !file_exists( $filePath ) )
        {
            return false;
        }

        try
        {
            $data = file_get_contents( $filePath ) ;
            return $data !== false && strlen( $data ) >= $this->ivLength ;
        }
        catch ( Exception )
        {
            return false;
        }
    }


    /**
     * Heuristically checks whether a file appears to be encrypted.
     *
     * Validates that the file has:
     * - at least IV length
     * - an IV not composed only of null bytes
     * - an IV not dominated by printable characters (indicating possible plaintext)
     *
     * This method gives a best-effort verification that a file was encrypted by this class.
     *
     * @param string $filePath Path to the file to check.
     *
     * @return bool True if the file likely contains encrypted content.
     *
     * @example
     * ```php
     * if ($crypto->isEncryptedFile('/file'))
     * {
     *     echo "Likely encrypted.";
     * }
     * ```
     */
    public function isEncryptedFile( string $filePath ): bool
    {
        if (!file_exists( $filePath ) )
        {
            return false;
        }

        try
        {
            $data = file_get_contents( $filePath ) ;
            if ( $data === false || strlen($data) < $this->ivLength )
            {
                return false;
            }

            // Extract IV (first bytes)
            $iv = substr( $data , 0 , $this->ivLength ) ;

            // Check that the IV doesn't only contain zeros
            if ( str_repeat("\0", $this->ivLength) === $iv )
            {
                return false;
            }

            // Check that the IV does not contain too many printable characters
            $printable = 0 ;
            for ( $i = 0; $i < $this->ivLength; $i++ )
            {
                $byte = ord( $iv[$i] ) ;
                if ( $byte >= 32 && $byte <= 126 ) // Printable ASCII range
                {
                    $printable++ ;
                }
            }

            // If more than 80%% of the bytes are printable characters,
            // it may look like text rather than an IV
            if ( $printable > $this->ivLength * 0.8 )
            {
                return false;
            }

            // If we've arrived here, it's probably an encrypted file
            return true;

        }
        catch ( Exception )
        {
            return false;
        }
    }
}