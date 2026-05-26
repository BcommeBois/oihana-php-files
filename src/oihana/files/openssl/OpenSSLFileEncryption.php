<?php

namespace oihana\files\openssl;

use Exception;
use InvalidArgumentException;
use RuntimeException;

use oihana\enums\Char;

use oihana\files\enums\FileExtension;
use oihana\files\exceptions\DirectoryException;
use oihana\files\exceptions\FileException;
use oihana\files\openssl\enums\EncryptionFormat;

use function oihana\files\assertDirectory;
use function oihana\files\assertFile;

/**
 * File-level symmetric encryption powered by OpenSSL.
 *
 * ## Security properties of this class
 *
 * **What it guarantees for files written by `encrypt()`** (format V2):
 *
 * 1. **Confidentiality** — AES-256-GCM. Without the passphrase, the plaintext
 *    is computationally infeasible to recover.
 * 2. **Integrity / authenticity** — every V2 file carries a 16-byte GCM
 *    authentication tag. Any tampering with the ciphertext makes `decrypt()`
 *    throw `RuntimeException` instead of returning corrupted plaintext.
 * 3. **Brute-force resistance on the passphrase** — the encryption key is
 *    derived via Argon2id (if `ext-sodium` is loaded) or PBKDF2-SHA256 with
 *    600 000 iterations. A per-file random salt prevents rainbow tables.
 * 4. **Per-file uniqueness** — a fresh random salt (16 B) + IV (12 B) is
 *    generated for each call to `encrypt()`. Encrypting the same plaintext
 *    twice with the same passphrase produces two unrelated ciphertexts.
 *
 * **What it does NOT guarantee:**
 *
 * - Forward secrecy: there is no per-session key. Anyone with the passphrase
 *   can decrypt every past file encrypted with that passphrase.
 * - Key revocation: changing the passphrase does not invalidate old files.
 *   You must re-encrypt them with the new passphrase.
 * - Protection from a compromised endpoint: if an attacker can read PHP
 *   memory while the passphrase is in use, they can recover it. The
 *   `__destruct()` cleanup is best-effort.
 * - Protection from the user choosing a weak passphrase: KDF only slows
 *   brute-force; it does not prevent it. Use long, random passphrases for
 *   sensitive data.
 *
 * ## Backward compatibility (legacy format)
 *
 * Files written by `oihana/php-files` ≤ 1.0 use the **legacy V1 format**:
 * a raw IV followed by AES-CBC ciphertext, with the passphrase used directly
 * (zero-padded) as the key, no MAC. Those files have no integrity protection,
 * but they remain **readable**: `decrypt()` auto-detects the absence of the
 * V2 magic header and falls back to the legacy code path.
 *
 * `encrypt()` always produces V2. To migrate a legacy file, simply call
 * `decrypt()` (reads V1) then `encrypt()` (writes V2).
 *
 * @example
 * ```php
 * use oihana\files\openssl\OpenSSLFileEncryption;
 *
 * $crypto = new OpenSSLFileEncryption('my-secret-passphrase');
 *
 * // Always produces a V2 file ('OPHE\x02…').
 * $encryptedPath = $crypto->encrypt('/path/to/file.txt');
 *
 * // Reads V2 or legacy V1 transparently.
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
     * @param string $passphrase Secret used to derive the encryption key. Must be non-empty.
     * @param string $cipher     Cipher used to **decrypt legacy V1 files**. New V2 files always use
     *                           {@see EncryptionFormat::DEFAULT_CIPHER}. Default `aes-256-cbc` matches
     *                           the historical behaviour of this class.
     *
     * @throws InvalidArgumentException If the passphrase is empty or the cipher is unsupported by OpenSSL.
     */
    public function __construct( string $passphrase, string $cipher = EncryptionFormat::LEGACY_CIPHER )
    {
        if ( $passphrase === Char::EMPTY )
        {
            throw new InvalidArgumentException("Passphrase cannot be empty") ;
        }

        if ( !in_array( $cipher , openssl_get_cipher_methods( true ) ) )
        {
            throw new InvalidArgumentException("Cipher method '$cipher' is not available");
        }

        if ( !in_array( EncryptionFormat::DEFAULT_CIPHER , openssl_get_cipher_methods( true ) ) )
        {
            throw new InvalidArgumentException( sprintf
            (
                'V2 cipher "%s" is not available in this OpenSSL build.' ,
                EncryptionFormat::DEFAULT_CIPHER
            )) ;
        }

        $this->cipher     = $cipher ;
        $this->passphrase = $passphrase ;
        $this->ivLength   = openssl_cipher_iv_length( $cipher ) ;
    }

    /**
     * Destructor.
     *
     * Best-effort wiping of the passphrase from memory. Uses `sodium_memzero()`
     * when available (true in-place wipe), falling back to a string overwrite
     * (which only releases the *current* reference — older copies created by
     * PHP's copy-on-write may persist in RAM).
     *
     * For strongly-secured deployments, prefer wiping the passphrase at the
     * application level (e.g. immediately after `encrypt()` / `decrypt()`)
     * rather than relying on this destructor.
     */
    public function __destruct()
    {
        if ( function_exists('sodium_memzero' ) )
        {
            sodium_memzero( $this->passphrase ) ;
        }
        else
        {
            $this->passphrase = str_repeat("\0" , strlen( $this->passphrase ) ) ;
        }
    }

    /**
     * The cipher method used to decrypt legacy V1 files.
     */
    private string $cipher ;

    /**
     * The passphrase used for encryption and decryption.
     */
    private string $passphrase ;

    /**
     * The IV length of the legacy cipher (used for V1 backward-compat).
     * @var int
     */
    public int $ivLength
    {
        get
        {
            return $this->ivLength;
        }
    }

    /**
     * Encrypts a file in V2 format (AES-256-GCM + KDF + magic header).
     *
     * Steps:
     * 1. Generate a 16-byte salt and a 12-byte IV with `random_bytes()`.
     * 2. Derive a 32-byte key from the passphrase + salt via the best
     *    available KDF (Argon2id if `ext-sodium` is loaded, PBKDF2 otherwise).
     * 3. Encrypt with AES-256-GCM, obtaining a 16-byte authentication tag.
     * 4. Write `MAGIC | VERSION | KDF | salt | IV | ciphertext | tag` to the output.
     *
     * @param string      $inputFile  Path to the plaintext input file.
     * @param string|null $outputFile Optional output path. If null, appends `.enc` to `$inputFile`.
     *
     * @return string Path to the encrypted output file.
     *
     * @throws FileException        If the input file is invalid.
     * @throws DirectoryException   If the output directory is not writable.
     * @throws RuntimeException     On read/write/encryption failure.
     */
    public function encrypt( string $inputFile , ?string $outputFile = null ) :string
    {
        assertFile( $inputFile ) ;

        if ( $outputFile === null )
        {
            $outputFile = $inputFile . FileExtension::ENCRYPTED ;
        }

        $plaintext = file_get_contents( $inputFile ) ;
        if ( $plaintext === false )
        {
            throw new RuntimeException('Encryption failed, unable to read the input file.' ) ;
        }

        try
        {
            $salt = random_bytes( EncryptionFormat::SALT_LENGTH ) ;
            $iv   = random_bytes( EncryptionFormat::GCM_IV_LENGTH ) ;
        }
        catch ( Exception )
        {
            throw new RuntimeException('Encryption failed: could not source cryptographically-secure randomness.') ;
        }

        $kdfAlgorithm = bestAvailableKdf() ;
        $key          = deriveKey( $this->passphrase , $salt , $kdfAlgorithm ) ;

        $tag = '' ;
        $ciphertext = openssl_encrypt
        (
            $plaintext ,
            EncryptionFormat::DEFAULT_CIPHER ,
            $key ,
            OPENSSL_RAW_DATA ,
            $iv ,
            $tag ,
            '' ,
            EncryptionFormat::GCM_TAG_LENGTH
        ) ;

        if ( $ciphertext === false )
        {
            throw new RuntimeException("Encryption failed, openssl_encrypt returned false.") ;
        }

        $outputDir = dirname( $outputFile ) ;
        assertDirectory( $outputDir , isWritable: true ) ;
        if ( file_exists( $outputFile ) && !is_writable( $outputFile ) )
        {
            throw new RuntimeException("Encryption failed, output file is not writable.") ;
        }

        $payload = EncryptionFormat::MAGIC
                 . chr( EncryptionFormat::VERSION_V2 )
                 . chr( $kdfAlgorithm )
                 . $salt
                 . $iv
                 . $ciphertext
                 . $tag ;

        $bytesWritten = @file_put_contents( $outputFile , $payload ) ;
        if ( $bytesWritten === false )
        {
            $error = error_get_last() ;
            throw new RuntimeException( "Encryption failed, file write failed: " . ( $error ? $error['message'] : 'unknown error' ) ) ;
        }

        return $outputFile ;
    }

    /**
     * Decrypts a previously encrypted file.
     *
     * Auto-detects the format by inspecting the first {@see EncryptionFormat::HEADER_LENGTH}
     * bytes:
     *
     * - If the file begins with `'OPHE\x02'` followed by a known KDF byte, it is
     *   treated as V2: the salt, IV, ciphertext and authentication tag are
     *   extracted; the tag is verified by `openssl_decrypt()` before the
     *   plaintext is returned.
     * - Otherwise the file is treated as legacy V1: the first `ivLength`
     *   bytes are the IV, the rest is the AES-CBC ciphertext, and the
     *   passphrase is used directly as the key.
     *
     * Either path produces the same exception type on failure:
     * `RuntimeException` with the same generic message (no oracle on
     * "wrong passphrase" vs "tampered ciphertext").
     *
     * @param string      $inputFile  Path to the encrypted file.
     * @param string|null $outputFile Optional output path. If null, strips `.enc` from `$inputFile`.
     *
     * @return string Path to the decrypted output file.
     *
     * @throws FileException        If the input file is invalid.
     * @throws RuntimeException     On read/write/decryption failure (including tampering, wrong passphrase).
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
            throw new RuntimeException("Failed to decrypt, unable to read the file." ) ;
        }

        $plaintext = $this->isV2Payload( $data )
                   ? $this->decryptV2( $data )
                   : $this->decryptLegacy( $data ) ;

        $outputDir = dirname( $outputFile ) ;
        if ( !is_dir( $outputDir ) )
        {
            throw new RuntimeException("Decryption failed, output directory does not exist.") ;
        }

        if ( !is_writable( $outputDir ) )
        {
            throw new RuntimeException("Decryption failed, output directory is not writable." ) ;
        }

        if ( file_exists( $outputFile ) && !is_writable( $outputFile ) )
        {
            throw new RuntimeException("Decryption failed, output file is not writable.") ;
        }

        $bytesWritten = @file_put_contents( $outputFile , $plaintext ) ;
        if ( $bytesWritten === false )
        {
            $error = error_get_last() ;
            throw new RuntimeException
            (
                "Decryption failed, file write failed: " .
                ( $error ? $error['message'] : 'unknown error' )
            );
        }

        return $outputFile ;
    }

    /**
     * Inspects the first bytes of an encrypted payload to determine whether it
     * is a V2 file (carries magic + version + KDF).
     */
    private function isV2Payload( string $data ) : bool
    {
        if ( strlen( $data ) < EncryptionFormat::HEADER_LENGTH )
        {
            return false ;
        }

        if ( substr( $data , 0 , EncryptionFormat::MAGIC_LENGTH ) !== EncryptionFormat::MAGIC )
        {
            return false ;
        }

        $version = ord( $data[ EncryptionFormat::MAGIC_LENGTH ] ) ;
        return $version === EncryptionFormat::VERSION_V2 ;
    }

    /**
     * Decrypts a V2 payload: reads the header, derives the key, validates the tag.
     */
    private function decryptV2( string $data ) : string
    {
        $minLength = EncryptionFormat::HEADER_LENGTH
                   + EncryptionFormat::SALT_LENGTH
                   + EncryptionFormat::GCM_IV_LENGTH
                   + EncryptionFormat::GCM_TAG_LENGTH ;

        if ( strlen( $data ) < $minLength )
        {
            throw new RuntimeException("Decryption failed, V2 payload is shorter than the minimum header." ) ;
        }

        $offset = EncryptionFormat::MAGIC_LENGTH + EncryptionFormat::VERSION_LENGTH ;

        $kdfAlgorithm = ord( $data[ $offset ] ) ;
        $offset += EncryptionFormat::KDF_LENGTH ;

        $salt    = substr( $data , $offset , EncryptionFormat::SALT_LENGTH ) ;
        $offset += EncryptionFormat::SALT_LENGTH ;

        $iv      = substr( $data , $offset , EncryptionFormat::GCM_IV_LENGTH ) ;
        $offset += EncryptionFormat::GCM_IV_LENGTH ;

        $rest       = substr( $data , $offset ) ;
        $tagOffset  = strlen( $rest ) - EncryptionFormat::GCM_TAG_LENGTH ;
        $ciphertext = substr( $rest , 0 , $tagOffset ) ;
        $tag        = substr( $rest , $tagOffset ) ;

        try
        {
            $key = deriveKey( $this->passphrase , $salt , $kdfAlgorithm ) ;
        }
        catch ( RuntimeException $e )
        {
            // Wrap KDF-level errors (unknown algo, sodium missing) under the same generic message.
            throw new RuntimeException
            (
                "Decryption failed due to incorrect passphrase or corrupted data." ,
                0 ,
                $e
            ) ;
        }

        $plaintext = openssl_decrypt
        (
            $ciphertext ,
            EncryptionFormat::DEFAULT_CIPHER ,
            $key ,
            OPENSSL_RAW_DATA ,
            $iv ,
            $tag ,
            ''
        ) ;

        if ( $plaintext === false )
        {
            throw new RuntimeException("Decryption failed due to incorrect passphrase or corrupted data.") ;
        }

        return $plaintext ;
    }

    /**
     * Decrypts a legacy V1 payload (no magic, no MAC, raw passphrase as key).
     *
     * Kept for backward-compatibility only. Files in this format have no
     * tampering detection; the integrity of the plaintext depends on the
     * integrity of the storage medium.
     */
    private function decryptLegacy( string $data ) : string
    {
        if ( strlen( $data ) < $this->ivLength )
        {
            throw new RuntimeException("Failed to decrypt, file is too short to contain IV and encrypted data." ) ;
        }

        $iv             = substr( $data , 0 , $this->ivLength ) ;
        $encryptedData  = substr( $data , $this->ivLength ) ;

        $plaintext = openssl_decrypt( $encryptedData , $this->cipher , $this->passphrase , OPENSSL_RAW_DATA , $iv ) ;
        if ( $plaintext === false )
        {
            throw new RuntimeException("Decryption failed due to incorrect passphrase or corrupted data.") ;
        }

        return $plaintext ;
    }

    /**
     * Checks if a file is large enough to *possibly* be a legacy V1 encrypted file.
     *
     * Verifies that the file has at least enough bytes to contain a legacy IV.
     * This is a cheap size-only check; it does NOT validate the V2 magic or
     * confirm anything about the content. Use {@see isEncryptedFile()} for a
     * heuristic content check.
     *
     * @param string $filePath Path to the file to check.
     *
     * @return bool True if the file exists and is at least `ivLength` bytes long.
     */
    public function hasEncryptedFileSize( string $filePath ): bool
    {
        if ( !is_file( $filePath ) )
        {
            return false ;
        }
        $size = @filesize( $filePath ) ;
        return $size !== false && $size >= $this->ivLength ;
    }

    /**
     * Heuristically checks whether a file appears to be encrypted by this class.
     *
     * For files written by `encrypt()` (V2), simply checks for the magic header.
     *
     * For legacy V1 files (no magic), falls back to the historical heuristic:
     * - file at least `ivLength` bytes long;
     * - IV not entirely zero;
     * - IV not dominated by printable characters (which would indicate plaintext).
     *
     * This method is **best-effort**. Some plaintext binary files (e.g. arbitrary
     * compressed data) may pass the legacy heuristic. Always treat its result as
     * a hint, never as a guarantee that decryption will succeed.
     *
     * @param string $filePath Path to the file to check.
     *
     * @return bool True if the file likely contains encrypted content.
     */
    public function isEncryptedFile( string $filePath ): bool
    {
        if ( !is_file( $filePath ) )
        {
            return false ;
        }

        try
        {
            $data = file_get_contents( $filePath ) ;
            if ( $data === false )
            {
                return false ;
            }

            // Fast path: V2 magic + minimum viable payload length
            if ( $this->isV2Payload( $data ) )
            {
                $minV2 = EncryptionFormat::HEADER_LENGTH
                       + EncryptionFormat::SALT_LENGTH
                       + EncryptionFormat::GCM_IV_LENGTH
                       + EncryptionFormat::GCM_TAG_LENGTH ;
                return strlen( $data ) >= $minV2 ;
            }

            // Legacy heuristic
            if ( strlen( $data ) < $this->ivLength )
            {
                return false ;
            }

            $iv = substr( $data , 0 , $this->ivLength ) ;
            if ( str_repeat("\0" , $this->ivLength ) === $iv )
            {
                return false ;
            }

            $printable = 0 ;
            for ( $i = 0 ; $i < $this->ivLength ; $i++ )
            {
                $byte = ord( $iv[ $i ] ) ;
                if ( $byte >= 32 && $byte <= 126 )
                {
                    $printable++ ;
                }
            }

            if ( $printable > $this->ivLength * 0.8 )
            {
                return false ;
            }

            return true ;
        }
        catch ( Exception )
        {
            return false ;
        }
    }
}
