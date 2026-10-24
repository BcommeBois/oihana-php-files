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

use SodiumException;
use function oihana\files\assertDirectory;
use function oihana\files\assertFile;

/**
 * File-level symmetric encryption powered by OpenSSL.
 *
 * ## Security properties of this class
 *
 * **What it guarantees for files written by `encrypt()`** (format V3):
 *
 * 1. **Confidentiality** — AES-256-GCM. Without the passphrase, the plaintext
 *    is computationally infeasible to recover.
 * 2. **Integrity / authenticity** — every block carries a 16-byte GCM tag, and every
 *    tag covers associated data naming the file, the block's position and whether it
 *    closes the stream ({@see encryptionBlockAad}). Tampering
 *    with a block, swapping two of them, dropping one, cutting the file short or
 *    splicing two files all make `decrypt()` throw instead of returning something.
 * 3. **Brute-force resistance on the passphrase** — the encryption key is
 *    derived via Argon2id (if `ext-sodium` is loaded) or PBKDF2-SHA256 with
 *    600 000 iterations. A per-file random salt prevents rainbow tables.
 * 4. **Per-file uniqueness** — a fresh random salt (16 B) + nonce prefix (8 B) is
 *    generated for each call to `encrypt()`. Encrypting the same plaintext
 *    twice with the same passphrase produces two unrelated ciphertexts.
 * 5. **Constant memory** — both directions read and write one block at a time, so a
 *    2 GB archive costs the same memory as a 2 kB note.
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
 * ## Why V3 streams, and V2 cannot
 *
 * V2 sealed the whole file under a single GCM tag. PHP exposes no incremental AEAD
 * API, so verifying that one tag means holding the entire ciphertext in memory — and
 * `openssl_decrypt()` then allocates the plaintext beside it. Measured on
 * 2026-10-24: a 300 MB file peaked at 1.18 GB, and a 1 GB file was killed by the
 * kernel's OOM killer, which leaves no exception and no stack trace to report. The
 * operation did not fail; it vanished.
 *
 * V3 cuts the plaintext into blocks of
 * {@see EncryptionFormat::DEFAULT_BLOCK_SIZE_EXPONENT} and seals each on its own, so
 * neither direction ever holds more than a block.
 *
 * ## Backward compatibility (legacy formats)
 *
 * - **V2** files (`OPHE\x02…`) remain readable, through the in-memory path, behind a
 *   guard that compares the file against the memory budget and **says** it cannot be
 *   read here rather than being killed half way.
 * - **V1** files, written by `oihana/php-files` ≤ 1.0, carry a raw IV followed by
 *   AES-CBC ciphertext, with the passphrase used directly as the key and no MAC. They
 *   have no tampering detection, but they still decrypt.
 *
 * `encrypt()` always produces V3. To migrate an older file, call `decrypt()` then
 * `encrypt()`.
 *
 * @example
 * ```php
 * use oihana\files\openssl\OpenSSLFileEncryption;
 *
 * $crypto = new OpenSSLFileEncryption('my-secret-passphrase');
 *
 * // Always produces a V3 file ('OPHE\x03…'), whatever its size.
 * $encryptedPath = $crypto->encrypt('/path/to/archive.tar.gz');
 *
 * // Reads V3, V2 and legacy V1 transparently.
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
     * @param string   $passphrase        Secret used to derive the encryption key. Must be non-empty.
     * @param string   $cipher            Cipher used to **decrypt legacy V1 files**. New V3 files always use
     *                                    {@see EncryptionFormat::DEFAULT_CIPHER}. Default `aes-256-cbc` matches
     *                                    the historical behaviour of this class.
     * @param int|null $maxInputBytes     Optional cap on the size of the files read by {@see encrypt()} and
     *                                    {@see decrypt()}. When set, a file whose size exceeds this value is
     *                                    rejected **before** being read ({@see RuntimeException}).
     *                                    Default `null` (no limit — historical behaviour).
     * @param int|null $blockSizeExponent Block size of the V3 files this instance writes, as a power of two.
     *                                    Default {@see EncryptionFormat::DEFAULT_BLOCK_SIZE_EXPONENT} (8 MiB).
     *                                    Validated here rather than at `encrypt()` time, so a bad value fails
     *                                    before a file has been opened.
     *
     * @throws InvalidArgumentException If the passphrase is empty, the cipher is unsupported by OpenSSL, or the
     *                                 block-size exponent is out of range.
     */
    public function __construct
    (
        string $passphrase                ,
        string $cipher       = EncryptionFormat::LEGACY_CIPHER ,
        ?int   $maxInputBytes = null ,
        ?int   $blockSizeExponent = null
    )
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
            // AES-256-GCM is always present in modern OpenSSL builds (ext-openssl required).
            // @codeCoverageIgnoreStart
            throw new InvalidArgumentException( sprintf
            (
                'V3 cipher "%s" is not available in this OpenSSL build.' ,
                EncryptionFormat::DEFAULT_CIPHER
            )) ;
            // @codeCoverageIgnoreEnd
        }

        $this->cipher            = $cipher ;
        $this->passphrase        = $passphrase ;
        $this->ivLength          = openssl_cipher_iv_length( $cipher ) ;
        $this->maxInputBytes     = $maxInputBytes ;
        $this->blockSizeExponent = assertBlockSizeExponent
        (
            $blockSizeExponent ?? EncryptionFormat::DEFAULT_BLOCK_SIZE_EXPONENT
        ) ;
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
     *
     * @throws SodiumException
     */
    public function __destruct()
    {
        if ( function_exists('sodium_memzero' ) )
        {
            sodium_memzero( $this->passphrase ) ;
        }
        // ext-sodium is loaded, so the fallback overwrite branch is never taken under test.
        // @codeCoverageIgnoreStart
        else
        {
            $this->passphrase = str_repeat("\0" , strlen( $this->passphrase ) ) ;
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * Suffix of the file written while an operation is still running.
     *
     * Neither direction writes to its destination until the last block has been
     * sealed, or the last tag verified. A V2 `decrypt()` could not leave a partial
     * file — it held the whole plaintext before writing a byte — but a streaming one
     * would, and a half-written plaintext looks exactly like a complete one. So the
     * payload lands beside the destination and is moved into place by a `rename()`,
     * which is atomic within a filesystem.
     */
    private const string PARTIAL_SUFFIX = '.part' ;

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
     * Optional cap on the size of the input files for {@see encrypt()} / {@see decrypt()}.
     * `null` means no limit.
     */
    private ?int $maxInputBytes ;

    /**
     * Block size of the V3 files this instance writes, as a power of two.
     */
    private int $blockSizeExponent ;

    /**
     * Encrypts a file in V3 format: AES-256-GCM, one sealed block at a time.
     *
     * Steps:
     * 1. Generate a 16-byte salt and an 8-byte nonce prefix with `random_bytes()`.
     * 2. Derive a 32-byte key from the passphrase + salt via the best available KDF
     *    (Argon2id if `ext-sodium` is loaded, PBKDF2 otherwise).
     * 3. Write the header: `MAGIC | VERSION | KDF | BLOCKEXP | salt | nonce prefix`.
     * 4. Read the plaintext one block at a time and append `ciphertext | tag` for each,
     *    the tag covering the header, the block index and the final-block flag.
     * 5. Move the finished file into place.
     *
     * An empty input still produces one frame — an empty block and its tag. Without it
     * a file could be reduced to its header and would then decrypt to nothing at all,
     * silently, which is the one truncation a per-block tag cannot catch.
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

        $this->assertWithinMaxInputBytes( $inputFile ) ;

        if ( $outputFile === null )
        {
            $outputFile = $inputFile . FileExtension::ENCRYPTED ;
        }

        $outputDir = dirname( $outputFile ) ;
        assertDirectory( $outputDir , isWritable: true ) ;

        if ( file_exists( $outputFile ) && !is_writable( $outputFile ) )
        {
            throw new RuntimeException("Encryption failed, output file is not writable.") ;
        }

        try
        {
            $salt        = random_bytes( EncryptionFormat::SALT_LENGTH ) ;
            $noncePrefix = random_bytes( EncryptionFormat::NONCE_PREFIX_LENGTH ) ;
        }
        // random_bytes only throws when no CSPRNG is available, which never happens here.
        // @codeCoverageIgnoreStart
        catch ( Exception )
        {
            throw new RuntimeException('Encryption failed: could not source cryptographically-secure randomness.') ;
        }
        // @codeCoverageIgnoreEnd

        $kdfAlgorithm = bestAvailableKdf() ;
        $key          = deriveKey( $this->passphrase , $salt , $kdfAlgorithm ) ;

        $header = EncryptionFormat::MAGIC
                . chr( EncryptionFormat::VERSION_V3 )
                . chr( $kdfAlgorithm )
                . chr( $this->blockSizeExponent )
                . $salt
                . $noncePrefix ;

        $size      = (int) filesize( $inputFile ) ;
        $blockSize = 1 << $this->blockSizeExponent ;
        $partial   = $outputFile . self::PARTIAL_SUFFIX ;

        $input = @fopen( $inputFile , 'rb' ) ;

        // Defensive: assertFile() already proved the input is a readable file.
        // @codeCoverageIgnoreStart
        if ( $input === false )
        {
            throw new RuntimeException('Encryption failed, unable to read the input file.' ) ;
        }
        // @codeCoverageIgnoreEnd

        $output = @fopen( $partial , 'wb' ) ;

        if ( $output === false )
        {
            fclose( $input ) ;
            throw new RuntimeException( 'Encryption failed, file write failed: ' . self::lastErrorMessage() ) ;
        }

        $complete = false ;

        try
        {
            $this->writeAll( $output , $header , 'Encryption failed' ) ;

            $offset = 0 ;
            $index  = 0 ;

            do
            {
                $block   = $this->readExactly( $input , min( $blockSize , $size - $offset ) ) ;
                $offset += strlen( $block ) ;
                $isFinal = $offset >= $size ;

                $tag        = '' ;
                $ciphertext = openssl_encrypt
                (
                    $block ,
                    EncryptionFormat::DEFAULT_CIPHER ,
                    $key ,
                    OPENSSL_RAW_DATA ,
                    encryptionBlockNonce( $noncePrefix , $index ) ,
                    $tag ,
                    encryptionBlockAad( $header , $index , $isFinal ) ,
                    EncryptionFormat::GCM_TAG_LENGTH
                ) ;

                // Defensive: openssl_encrypt does not fail with a valid AEAD cipher, key and nonce.
                // @codeCoverageIgnoreStart
                if ( $ciphertext === false )
                {
                    throw new RuntimeException("Encryption failed, openssl_encrypt returned false.") ;
                }
                // @codeCoverageIgnoreEnd

                // Written separately rather than concatenated: a `$ciphertext . $tag` would
                // allocate a third copy of the block, which is the whole point of streaming.
                $this->writeAll( $output , $ciphertext , 'Encryption failed' ) ;
                $this->writeAll( $output , $tag , 'Encryption failed' ) ;

                $index++ ;
            }
            while ( !$isFinal ) ;

            $complete = true ;
        }
        finally
        {
            fclose( $input ) ;
            fclose( $output ) ;

            if ( !$complete )
            {
                @unlink( $partial ) ;
            }
        }

        if ( !@rename( $partial , $outputFile ) )
        {
            @unlink( $partial ) ;
            throw new RuntimeException( 'Encryption failed, file write failed: ' . self::lastErrorMessage() ) ;
        }

        return $outputFile ;
    }

    /**
     * Decrypts a previously encrypted file.
     *
     * Auto-detects the format from the first bytes
     * ({@see OpenSSLFileEncryption::detectFormatVersion()}):
     *
     * - `'OPHE\x03'` — V3. Read one frame at a time, each tag verified against the
     *   header, the block index and the final-block flag before its plaintext is
     *   written. Constant memory.
     * - `'OPHE\x02'` — V2. Read whole, behind the memory guard
     *   ({@see OpenSSLFileEncryption::assertLegacyFitsInMemory()}).
     * - anything else — legacy V1: the first `ivLength` bytes are the IV, the rest is
     *   AES-CBC ciphertext, the passphrase is the key. Also behind the guard.
     *
     * Every path produces the same exception type on a cryptographic failure:
     * `RuntimeException` with the same generic message (no oracle on "wrong
     * passphrase" vs "tampered ciphertext").
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

        $this->assertWithinMaxInputBytes( $inputFile ) ;

        if ( $outputFile === null )
        {
            $outputFile = str_replace( FileExtension::ENCRYPTED , Char::EMPTY , $inputFile ) ;
        }

        // Checked before anything is read, and no longer after the plaintext exists:
        // a streaming read has already written part of its output by then.

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

        $size    = (int) filesize( $inputFile ) ;
        $version = $this->detectFormatVersion( $this->readHeader( $inputFile , EncryptionFormat::V3_HEADER_LENGTH ) ) ;

        if ( $version === EncryptionFormat::VERSION_V3 )
        {
            $this->decryptV3( $inputFile , $outputFile , $size ) ;

            return $outputFile ;
        }

        $this->assertLegacyFitsInMemory( $inputFile , $version , $size ) ;

        $data = file_get_contents( $inputFile ) ;

        if ( $data === false )
        {
            // Defensive: assertFile() already proved the input is a readable file.
            // @codeCoverageIgnoreStart
            throw new RuntimeException("Failed to decrypt, unable to read the file." ) ;
            // @codeCoverageIgnoreEnd
        }

        $plaintext = $version === EncryptionFormat::VERSION_V2
                   ? $this->decryptV2( $data )
                   : $this->decryptLegacy( $data ) ;

        $bytesWritten = @file_put_contents( $outputFile , $plaintext ) ;

        if ( $bytesWritten === false )
        {
            throw new RuntimeException( 'Decryption failed, file write failed: ' . self::lastErrorMessage() ) ;
        }

        return $outputFile ;
    }

    /**
     * Reads at most `$length` bytes from the start of a file.
     *
     * The whole reason {@see isEncryptedFile()} and {@see decrypt()} no longer begin
     * with `file_get_contents()`: deciding what a file is takes 31 bytes, and the
     * version that read all of them turned a 2 GB archive into 2 GB of memory to
     * answer a yes-or-no question.
     *
     * @return string What was read, possibly shorter than asked, or `''` when the file
     *                cannot be opened.
     */
    private function readHeader( string $filePath , int $length ) : string
    {
        $handle = @fopen( $filePath , 'rb' ) ;

        // Defensive: callers have already established the file exists and is readable.
        // @codeCoverageIgnoreStart
        if ( $handle === false )
        {
            return '' ;
        }
        // @codeCoverageIgnoreEnd

        $header = @fread( $handle , $length ) ;

        fclose( $handle ) ;

        return is_string( $header ) ? $header : '' ;
    }

    /**
     * Reports which of the three formats a file's first bytes announce.
     *
     * A file that does not start with the magic is V1 by elimination — V1 files carry
     * no header at all, so there is nothing to recognise them by.
     *
     * @return int One of {@see EncryptionFormat::VERSION_V1}, `VERSION_V2`, `VERSION_V3`.
     *
     * @throws RuntimeException If the file bears the magic but names a version this
     *                          library does not read. Guessing would be worse: a file
     *                          written by a newer release must not be run through the
     *                          V1 path, which has no integrity check and would emit
     *                          whatever AES-CBC made of it.
     */
    private function detectFormatVersion( string $header ) : int
    {
        if ( strlen( $header ) < EncryptionFormat::HEADER_LENGTH )
        {
            return EncryptionFormat::VERSION_V1 ;
        }

        if ( substr( $header , 0 , EncryptionFormat::MAGIC_LENGTH ) !== EncryptionFormat::MAGIC )
        {
            return EncryptionFormat::VERSION_V1 ;
        }

        $version = ord( $header[ EncryptionFormat::MAGIC_LENGTH ] ) ;

        if ( $version === EncryptionFormat::VERSION_V3 || $version === EncryptionFormat::VERSION_V2 )
        {
            return $version ;
        }

        throw new RuntimeException( sprintf
        (
            'Decryption failed, unsupported format version %d: this file was written by a newer version of this library.' ,
            $version
        )) ;
    }

    /**
     * Decrypts a V3 file, one frame at a time, into `$outputFile`.
     *
     * Frame boundaries come from arithmetic, not from a length field: every frame but
     * the last is exactly `blockSize + 16` bytes, so the reader knows where each one
     * ends from the declared block size and the file size. Which is also how it knows
     * which frame is the last — and that knowledge is part of what each tag covers, so
     * a file cut short presents a frame sealed as non-final where a final one belongs.
     *
     * @param int $size The file size, read once by the caller.
     *
     * @throws RuntimeException On a malformed header, a failed tag, or a write failure.
     */
    private function decryptV3( string $inputFile , string $outputFile , int $size ) : void
    {
        if ( $size < EncryptionFormat::V3_HEADER_LENGTH + EncryptionFormat::GCM_TAG_LENGTH )
        {
            throw new RuntimeException("Decryption failed, V3 file is shorter than a header and one frame.") ;
        }

        $input = @fopen( $inputFile , 'rb' ) ;

        // Defensive: assertFile() already proved the input is a readable file.
        // @codeCoverageIgnoreStart
        if ( $input === false )
        {
            throw new RuntimeException("Failed to decrypt, unable to read the file." ) ;
        }
        // @codeCoverageIgnoreEnd

        $partial = $outputFile . self::PARTIAL_SUFFIX ;

        try
        {
            $header = $this->readExactly( $input , EncryptionFormat::V3_HEADER_LENGTH ) ;

            $offset       = EncryptionFormat::MAGIC_LENGTH + EncryptionFormat::VERSION_LENGTH ;
            $kdfAlgorithm = ord( $header[ $offset ] ) ;

            $offset  += EncryptionFormat::KDF_LENGTH ;
            $exponent = ord( $header[ $offset ] ) ;

            $offset += EncryptionFormat::BLOCK_SIZE_EXPONENT_LENGTH ;
            $salt    = substr( $header , $offset , EncryptionFormat::SALT_LENGTH ) ;

            $offset     += EncryptionFormat::SALT_LENGTH ;
            $noncePrefix = substr( $header , $offset , EncryptionFormat::NONCE_PREFIX_LENGTH ) ;

            // Bounded before it is shifted, and before a byte has been authenticated:
            // the exponent is one byte of an unverified header, so it can say 255.
            try
            {
                $blockSize = 1 << assertBlockSizeExponent( $exponent ) ;
            }
            catch ( InvalidArgumentException $e )
            {
                throw new RuntimeException
                (
                    'Decryption failed, the file declares an unusable block size. ' . $e->getMessage() ,
                    0 ,
                    $e
                ) ;
            }

            $this->assertKdfAvailable( $kdfAlgorithm ) ;

            try
            {
                $key = deriveKey( $this->passphrase , $salt , $kdfAlgorithm ) ;
            }
            catch ( RuntimeException $e )
            {
                // Wrap what is left — an unknown identifier — under the generic message.
                throw new RuntimeException
                (
                    "Decryption failed due to incorrect passphrase or corrupted data." ,
                    0 ,
                    $e
                ) ;
            }

            $output = @fopen( $partial , 'wb' ) ;

            if ( $output === false )
            {
                throw new RuntimeException( 'Decryption failed, file write failed: ' . self::lastErrorMessage() ) ;
            }

            $complete = false ;

            try
            {
                $cursor = EncryptionFormat::V3_HEADER_LENGTH ;
                $index  = 0 ;

                do
                {
                    $remaining = $size - $cursor ;

                    if ( $remaining < EncryptionFormat::GCM_TAG_LENGTH )
                    {
                        throw new RuntimeException("Decryption failed, the last frame is shorter than its authentication tag.") ;
                    }

                    $isFinal     = $remaining <= $blockSize + EncryptionFormat::GCM_TAG_LENGTH ;
                    $frameLength = $isFinal ? $remaining : $blockSize + EncryptionFormat::GCM_TAG_LENGTH ;

                    $ciphertext = $this->readExactly( $input , $frameLength - EncryptionFormat::GCM_TAG_LENGTH ) ;
                    $tag        = $this->readExactly( $input , EncryptionFormat::GCM_TAG_LENGTH ) ;

                    $plaintext = openssl_decrypt
                    (
                        $ciphertext ,
                        EncryptionFormat::DEFAULT_CIPHER ,
                        $key ,
                        OPENSSL_RAW_DATA ,
                        encryptionBlockNonce( $noncePrefix , $index ) ,
                        $tag ,
                        encryptionBlockAad( $header , $index , $isFinal )
                    ) ;

                    if ( $plaintext === false )
                    {
                        throw new RuntimeException("Decryption failed due to incorrect passphrase or corrupted data.") ;
                    }

                    $this->writeAll( $output , $plaintext , 'Decryption failed' ) ;

                    $cursor += $frameLength ;
                    $index++ ;
                }
                while ( !$isFinal ) ;

                $complete = true ;
            }
            finally
            {
                fclose( $output ) ;

                if ( !$complete )
                {
                    @unlink( $partial ) ;
                }
            }
        }
        finally
        {
            fclose( $input ) ;
        }

        if ( !@rename( $partial , $outputFile ) )
        {
            @unlink( $partial ) ;
            throw new RuntimeException( 'Decryption failed, file write failed: ' . self::lastErrorMessage() ) ;
        }
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

        $this->assertKdfAvailable( $kdfAlgorithm ) ;

        try
        {
            $key = deriveKey( $this->passphrase , $salt , $kdfAlgorithm ) ;
        }
        catch ( RuntimeException $e )
        {
            // Wrap what is left — an unknown identifier — under the generic message.
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
     * Refuses a V1/V2 file that the memory budget cannot hold, and says why.
     *
     * The two legacy formats seal their whole payload under one tag, and PHP has no
     * incremental AEAD API to verify it in pieces — so reading one means holding the
     * ciphertext, a copy of it, and the plaintext, about
     * {@see EncryptionFormat::LEGACY_MEMORY_FACTOR} times the file.
     *
     * Past the budget there are two outcomes, and the bad one is the quiet one. With a
     * `memory_limit` set, PHP throws a fatal error naming the limit. With
     * `memory_limit = -1`, the kernel's OOM killer takes the process down: no
     * exception, no stack trace, no log line from PHP — a backup that neither
     * succeeded nor failed. This refusal replaces that silence with a sentence.
     *
     * No-op when the budget is unknown ({@see availableMemoryBytes}
     * returns `null`, as it does on macOS without a `memory_limit`): refusing on a
     * number nobody has would break reads that work today.
     *
     * @throws RuntimeException If the file needs more memory than the budget allows.
     */
    private function assertLegacyFitsInMemory( string $inputFile , int $version , int $size ) : void
    {
        $budget = $this->memoryBudget() ;

        if ( $budget === null )
        {
            return ;
        }

        $needed = $size * EncryptionFormat::LEGACY_MEMORY_FACTOR ;

        if ( $needed <= $budget )
        {
            return ;
        }

        throw new RuntimeException( sprintf
        (
            'Decryption failed: "%s" is a V%d file of %d bytes, and reading one needs about %d bytes — ' .
            'roughly %d times its size, because the format seals its whole payload under a single ' .
            'authentication tag that PHP cannot verify a block at a time. The memory budget here is %d bytes. ' .
            'Raise memory_limit, or decrypt this file on a machine with more memory; files written in V3 ' .
            'stream at constant memory and are not affected.' ,
            $inputFile ,
            $version ,
            $size ,
            $needed ,
            EncryptionFormat::LEGACY_MEMORY_FACTOR ,
            $budget
        )) ;
    }

    /**
     * How many bytes this process may reasonably allocate, or `null` when it cannot be told.
     *
     * A method rather than a direct call so a test can state a budget instead of
     * inheriting the runner's.
     */
    protected function memoryBudget() : ?int
    {
        return availableMemoryBytes() ;
    }

    /**
     * Refuses a file whose KDF this host cannot run, and says which extension is missing.
     *
     * The failure this replaces was the most misleading one in the class. A file
     * written where `ext-sodium` is loaded names Argon2id in its header; read where it
     * is not, `deriveKey()` threw, the message was wrapped under *"incorrect
     * passphrase or corrupted data"*, and the real cause stayed in a previous
     * exception that no command line prints. Measured on 2026-10-24 with
     * `disable_functions=sodium_crypto_pwhash`: an intact archive, the right
     * passphrase, and a report sending the operator after a lost secret or a corrupt
     * file. The extension takes a minute to install; the hunt it caused did not.
     *
     * Naming it gives nothing away. The missing extension is a property of the reader's
     * own machine, which an attacker reading their own machine already knows, and it
     * says nothing about the key or the plaintext — unlike *why* a tag failed, which
     * is why that one stays generic.
     *
     * @throws RuntimeException If the file's KDF needs an extension that is not loaded.
     */
    private function assertKdfAvailable( int $kdfAlgorithm ) : void
    {
        if ( $this->isKdfAvailable( $kdfAlgorithm ) )
        {
            return ;
        }

        throw new RuntimeException( sprintf
        (
            'Decryption failed: the key of this file was derived with Argon2id (KDF identifier %d), ' .
            'which requires the sodium extension, and it is not loaded here. The passphrase is not in ' .
            'question — every file names its KDF in its header, and this one names a KDF this host ' .
            'cannot run. Install ext-sodium, or decrypt the file on a host that has it. Files written ' .
            'without sodium use PBKDF2-SHA256, which is native and reads anywhere.' ,
            $kdfAlgorithm
        )) ;
    }

    /**
     * Whether the KDF a file names can be run here.
     *
     * A method rather than a direct call so a test can state the answer instead of
     * inheriting the suite's own extensions.
     */
    protected function isKdfAvailable( int $kdfAlgorithm ) : bool
    {
        return kdfIsAvailable( $kdfAlgorithm ) ;
    }

    /**
     * Reads exactly `$length` bytes, however many reads that takes.
     *
     * A short `fread()` would otherwise produce a frame smaller than the declared
     * block size, and a reader that computes frame boundaries from that size would
     * then parse the rest of the file at the wrong offsets.
     *
     * @param resource $handle
     *
     * @throws RuntimeException If the stream ends early.
     */
    private function readExactly( $handle , int $length ) : string
    {
        $buffer = '' ;

        while ( strlen( $buffer ) < $length )
        {
            $chunk = @fread( $handle , $length - strlen( $buffer ) ) ;

            // Reachable only if the file shrinks between its size being read and this read.
            // @codeCoverageIgnoreStart
            if ( $chunk === false || $chunk === '' )
            {
                throw new RuntimeException("Failed to decrypt, the file ended before the expected number of bytes." ) ;
            }
            // @codeCoverageIgnoreEnd

            $buffer .= $chunk ;
        }

        return $buffer ;
    }

    /**
     * Writes a whole string, however many writes that takes.
     *
     * @param resource $handle
     * @param string   $context Message prefix, so a failure reads as the operation the
     *                          caller is performing rather than as a generic write error.
     *
     * @throws RuntimeException If the stream stops accepting bytes.
     */
    private function writeAll( $handle , string $data , string $context ) : void
    {
        $length = strlen( $data ) ;
        $offset = 0 ;

        while ( $offset < $length )
        {
            $written = @fwrite( $handle , substr( $data , $offset ) ) ;

            // Reachable on a full disk or a broken pipe, neither of which a test can stage portably.
            // @codeCoverageIgnoreStart
            if ( $written === false || $written === 0 )
            {
                throw new RuntimeException( $context . ', file write failed: ' . self::lastErrorMessage() ) ;
            }
            // @codeCoverageIgnoreEnd

            $offset += $written ;
        }
    }

    /**
     * The last PHP error message, or a placeholder when there is none.
     */
    private static function lastErrorMessage() : string
    {
        $error = error_get_last() ;

        return $error ? $error[ 'message' ] : 'unknown error' ;
    }

    /**
     * Rejects an input file whose size exceeds the configured `$maxInputBytes` cap.
     *
     * No-op when `$maxInputBytes` is `null` (default).
     *
     * @throws RuntimeException If the file size strictly exceeds the cap.
     */
    private function assertWithinMaxInputBytes( string $inputFile ) : void
    {
        if ( $this->maxInputBytes === null )
        {
            return ;
        }

        $size = filesize( $inputFile ) ;
        if ( $size > $this->maxInputBytes )
        {
            throw new RuntimeException( sprintf
            (
                'OpenSSLFileEncryption: file "%s" is %d bytes, exceeds maxInputBytes %d.' ,
                $inputFile ,
                $size ,
                $this->maxInputBytes
            )) ;
        }
    }

    /**
     * Checks if a file is large enough to *possibly* be a legacy V1 encrypted file.
     *
     * Verifies that the file has at least enough bytes to contain a legacy IV.
     * This is a cheap size-only check; it does NOT validate the magic or
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
     * Reads the first bytes only — never the whole file, which is what the version
     * before this one did, at the cost of the file's size in memory to answer a
     * yes-or-no question.
     *
     * For files bearing the `OPHE` magic, the answer is the magic plus a minimum
     * length for the version named. A version this library does not read still
     * answers true: the file is one of ours, it is simply newer.
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

        $size = @filesize( $filePath ) ;

        // Defensive: is_file() already passed; an unreadable file here is a TOCTOU race.
        // @codeCoverageIgnoreStart
        if ( $size === false )
        {
            return false ;
        }
        // @codeCoverageIgnoreEnd

        $header = $this->readHeader( $filePath , max( EncryptionFormat::V3_HEADER_LENGTH , $this->ivLength ) ) ;

        if
        (
            strlen( $header ) >= EncryptionFormat::HEADER_LENGTH &&
            substr( $header , 0 , EncryptionFormat::MAGIC_LENGTH ) === EncryptionFormat::MAGIC
        )
        {
            return match ( ord( $header[ EncryptionFormat::MAGIC_LENGTH ] ) )
            {
                EncryptionFormat::VERSION_V3 =>
                    $size >= EncryptionFormat::V3_HEADER_LENGTH + EncryptionFormat::GCM_TAG_LENGTH ,

                EncryptionFormat::VERSION_V2 =>
                    $size >= EncryptionFormat::HEADER_LENGTH
                           + EncryptionFormat::SALT_LENGTH
                           + EncryptionFormat::GCM_IV_LENGTH
                           + EncryptionFormat::GCM_TAG_LENGTH ,

                default => true ,
            } ;
        }

        // Legacy heuristic

        if ( strlen( $header ) < $this->ivLength )
        {
            return false ;
        }

        $iv = substr( $header , 0 , $this->ivLength ) ;

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
}
