<?php

namespace oihana\files;

use Exception;
use InvalidArgumentException;
use RuntimeException;

/**
 * Class OpenSSLFileEncryption
 * This class provides methods to encrypt and decrypt files using OpenSSL.
 * @package oihana\files
 */
class OpenSSLFileEncryption
{
    /**
     * Creates a new OpenSSLFileEncryption instance.
     * @param string $passphrase The key to encrypt the file.
     * @param string $cipher The cipher method. For a list of available cipher methods, use {@see openssl_get_cipher_methods()}.
     * @throws InvalidArgumentException If the cipher method is not available or passphrase is empty
     */
    public function __construct( string $passphrase, string $cipher = 'aes-256-cbc' )
    {
        if ( !in_array( $cipher , openssl_get_cipher_methods(true ) ) )
        {
            throw new InvalidArgumentException("Cipher method '{$cipher}' is not available");
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
     * Cleans sensitive data when object is destroyed
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
     * Encrypt a file with the OpenSSL tool.
     *
     * This method reads the input file, encrypts its contents using OpenSSL, and writes the encrypted data to the output file.
     * The encrypted data includes the initialization vector (IV) prepended to the encrypted data.
     *
     * @param string $inputFile The path to the file to be encrypted.
     * @param string $outputFile The path where the encrypted file will be written.
     * @return true Returns true on successful encryption.
     * @throws RuntimeException If the input file cannot be read, encryption fails, or the output file cannot be written.
     */
    public function encrypt( string $inputFile , string $outputFile ):bool
    {
        if( !file_exists( $inputFile ) )
        {
            throw new RuntimeException( 'Encryption failed, the input file not exist.' ) ;
        }

        $data = file_get_contents( $inputFile ) ;
        if ( $data === false )
        {
            throw new RuntimeException( 'Encryption failed, unable to read the file.' ) ;
        }

        // Generate secure IV
        $iv = openssl_random_pseudo_bytes( $this->ivLength ,  $cryptoStrong ) ;
        if ( $iv === false || !$cryptoStrong )
        {
            throw new RuntimeException("Encryption failed: could not generate secure IV");
        }

        // Encrypt data
        $encrypted = openssl_encrypt( $data, $this->cipher, $this->passphrase, OPENSSL_RAW_DATA, $iv );
        if ( $encrypted === false )
        {
            throw new RuntimeException("Failed to encrypt the file'." ) ;
        }

        // Prepare output
        $outputDir = dirname( $outputFile ) ;

        // Check/create output directory
        if ( !is_dir( $outputDir ) )
        {
            throw new RuntimeException("Encryption failed, output directory does not exist." ) ;
        }

        if ( !is_writable( $outputDir ) )
        {
            throw new RuntimeException("Encryption failed, output directory is not writable." ) ;
        }

        if ( file_exists( $outputFile ) && !is_writable( $outputFile ) )
        {
            throw new RuntimeException("Encryption failed, output file is not writable.") ;
        }

        // Write encrypted data
        $bytesWritten = @file_put_contents( $outputFile , $iv . $encrypted ) ;
        if( $bytesWritten === false )
        {
            $error = error_get_last() ;
            throw new RuntimeException( "Encryption failed, file write failed: " . ( $error ? $error['message'] : 'unknown error' ) );
        }

        return true;
    }

    /**
     * Decrypt a file with the OpenSSL tool.
     *
     * This method reads the input file, extracts the initialization vector (IV) from the beginning of the file,
     * decrypts the remaining data using OpenSSL, and writes the decrypted data to the output file.
     *
     * @param string $inputFile The path to the file to be decrypted.
     * @param string $outputFile The path where the decrypted file will be written.
     * @return true Returns true on successful decryption.
     * @throws RuntimeException If the input file cannot be read, decryption fails (due to incorrect passphrase or corrupted data), or the output file cannot be written.
     */
    public function decrypt( string $inputFile , string $outputFile ) :true
    {
        // Validate input file
        if ( !file_exists( $inputFile ) )
        {
            throw new RuntimeException("Failed to decrypt, the input file does not exist." ) ;
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

        return true;
    }

    /**
     * Checks if a file has the minimum size to be an encrypted file.
     *
     * This makes a best-effort check that the file contains at least an IV
     * of the expected length. Note that this doesn't guarantee that the file
     * is actually encrypted or can be decrypted, but only that it's the right size.
     *
     * @param string $filePath Path to the file to check
     * @return bool True if the file has the minimum size to be an encrypted file
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
     * Checks if a file appears to be encrypted by this class.
     *
     * This makes a best-effort check that the file contains at least an IV
     * of the expected length AND that the IV bytes appear random.
     *
     * @param string $filePath Path to the file to check
     * @return bool True if the file appears to be encrypted
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
            if ($printable > $this->ivLength * 0.8) {
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