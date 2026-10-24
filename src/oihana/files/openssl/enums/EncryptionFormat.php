<?php

namespace oihana\files\openssl\enums;

use oihana\reflect\traits\ConstantsTrait;

/**
 * On-disk file format constants for {@see \oihana\files\openssl\OpenSSLFileEncryption}.
 *
 * **V3** is the chunked, streaming format produced by `encrypt()`. **V2** (a single
 * GCM blob) and **V1** (CBC, no MAC, no magic) are read-only legacy formats.
 *
 * Layout of a V3 file:
 * ```
 * ┌─────────┬─────────┬─────────┬──────────┬──────────┬──────────────┐
 * │ MAGIC   │ VERSION │ KDF     │ BLOCKEXP │ SALT     │ NONCE PREFIX │
 * │ 4 bytes │ 1 byte  │ 1 byte  │ 1 byte   │ 16 bytes │ 8 bytes      │
 * └─────────┴─────────┴─────────┴──────────┴──────────┴──────────────┘
 * followed by one or more frames:
 * ┌──────────────────────────┬──────────┐
 * │ encrypted block          │ GCM TAG  │   × N
 * │ ≤ 2^BLOCKEXP bytes       │ 16 bytes │
 * └──────────────────────────┴──────────┘
 * ```
 *
 * The nonce of block *i* is `NONCE PREFIX ‖ pack('N', i)` — 8 + 4 = 12 bytes, the
 * NIST-recommended GCM IV length. The counter never repeats under one key, so no
 * two blocks of one file share a nonce.
 *
 * Each block is authenticated with associated data binding **the whole header, the
 * block index and a final-block flag**:
 * ```
 * AAD(i) = header ‖ pack('N', i) ‖ chr( isFinal ? 1 : 0 )
 * ```
 * Without that binding every frame would authenticate on its own, and truncating a
 * file, reordering two frames or splicing two files together would all go
 * undetected. The final flag is decided **at read time** by the cursor position
 * against the file size, so a frame written as non-final but read as the last one
 * fails its tag — which is exactly what truncation looks like.
 *
 * Layout of a V2 file (read-only):
 * ```
 * ┌─────────┬─────────┬─────────┬──────────┬──────────┬──────────────────────┐
 * │ MAGIC   │ VERSION │ KDF     │ SALT     │ IV       │ ciphertext + TAG     │
 * │ 4 bytes │ 1 byte  │ 1 byte  │ 16 bytes │ 12 bytes │ variable + 16 bytes  │
 * └─────────┴─────────┴─────────┴──────────┴──────────┴──────────────────────┘
 * ```
 *
 * The KDF byte names how the encryption key was derived from the passphrase, so a
 * reader never has to guess, and a file stays readable after this library's default
 * changes.
 *
 * What it does **not** do is make that KDF available. {@see EncryptionFormat::KDF_ARGON2ID}
 * needs `ext-sodium` at decryption time exactly as much as at encryption time, and
 * `ext-sodium` is a suggestion of this package rather than a requirement — so a file
 * written where it is loaded cannot be read where it is not. Measured on 2026-10-24,
 * that read reported *"incorrect passphrase or corrupted data"* on an intact archive
 * with the right passphrase; it now names the missing extension instead
 * ({@see \oihana\files\openssl\kdfIsAvailable()}).
 * {@see EncryptionFormat::KDF_PBKDF2_SHA256} is native to PHP and reads anywhere.
 *
 * @package oihana\files\openssl\enums
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
class EncryptionFormat
{
    use ConstantsTrait ;

    /**
     * 4-byte ASCII magic identifying an Oihana PHP Encryption file.
     */
    public const string MAGIC = 'OPHE' ;

    /**
     * Length of {@see EncryptionFormat::MAGIC} in bytes.
     */
    public const int MAGIC_LENGTH = 4 ;

    /**
     * Legacy on-disk format version: raw IV + AES-CBC ciphertext, no magic, no MAC.
     *
     * V1 files carry no header at all, so this value is never written to disk — it is
     * what the format detection reports for a file that does not begin with
     * {@see EncryptionFormat::MAGIC}.
     */
    public const int VERSION_V1 = 1 ;

    /**
     * Legacy on-disk format version: a single AES-256-GCM blob with one tag.
     *
     * Read-only. PHP exposes no incremental AEAD API, so a single-tag blob cannot be
     * verified without holding it whole in memory — which is why V3 exists.
     */
    public const int VERSION_V2 = 2 ;

    /**
     * Current on-disk format version, written right after the magic.
     *
     * V3 is chunked: the plaintext is cut into blocks of {@see EncryptionFormat::DEFAULT_BLOCK_SIZE_EXPONENT}
     * and each block is sealed on its own, so both `encrypt()` and `decrypt()` run at
     * constant memory whatever the file size.
     */
    public const int VERSION_V3 = 3 ;

    /**
     * Length of the version byte.
     */
    public const int VERSION_LENGTH = 1 ;

    /**
     * Length of the KDF-identifier byte (right after the version).
     */
    public const int KDF_LENGTH = 1 ;

    /**
     * Total length of the V2 format prefix (MAGIC + VERSION + KDF) in bytes.
     */
    public const int HEADER_LENGTH = 6 ;

    /**
     * KDF salt length in bytes (16 = 128 bits, NIST recommendation).
     */
    public const int SALT_LENGTH = 16 ;

    /**
     * AES-GCM IV length in bytes (12 = 96 bits, NIST SP 800-38D recommendation).
     */
    public const int GCM_IV_LENGTH = 12 ;

    /**
     * AES-GCM authentication tag length in bytes (16 = 128 bits, maximum security).
     */
    public const int GCM_TAG_LENGTH = 16 ;

    /**
     * Derived symmetric key length in bytes (32 = 256 bits, fits AES-256).
     */
    public const int KEY_LENGTH = 32 ;

    /**
     * KDF identifier: Argon2id (memory-hard, GPU-resistant).
     * Requires `ext-sodium` at both encrypt and decrypt time.
     */
    public const int KDF_ARGON2ID = 1 ;

    /**
     * KDF identifier: PBKDF2-SHA256.
     * Always available (native PHP).
     */
    public const int KDF_PBKDF2_SHA256 = 2 ;

    /**
     * PBKDF2 iteration count.
     * 600 000 iterations of SHA-256 ≈ 250 ms on modern hardware.
     * Matches OWASP 2023+ recommendation.
     */
    public const int PBKDF2_ITERATIONS = 600_000 ;

    /**
     * Default AEAD cipher used when writing V2 files.
     * AES-256-GCM is the modern standard: confidentiality + integrity in a single primitive.
     */
    public const string DEFAULT_CIPHER = 'aes-256-gcm' ;

    /**
     * Default cipher used when reading legacy V1 files (no magic header).
     * Matches the historical OpenSSLFileEncryption constructor default.
     */
    public const string LEGACY_CIPHER = 'aes-256-cbc' ;

    /**
     * Length of the block-size-exponent byte of a V3 header (right after the KDF byte).
     */
    public const int BLOCK_SIZE_EXPONENT_LENGTH = 1 ;

    /**
     * Length of the per-file random nonce prefix of a V3 header, in bytes.
     *
     * {@see EncryptionFormat::NONCE_PREFIX_LENGTH} + {@see EncryptionFormat::BLOCK_COUNTER_LENGTH}
     * must equal {@see EncryptionFormat::GCM_IV_LENGTH}.
     */
    public const int NONCE_PREFIX_LENGTH = 8 ;

    /**
     * Length of the big-endian block counter appended to the nonce prefix, in bytes.
     *
     * 4 bytes cap a file at 2^32 blocks — 32 EiB at the default block size.
     */
    public const int BLOCK_COUNTER_LENGTH = 4 ;

    /**
     * Total length of a V3 header in bytes:
     * MAGIC (4) + VERSION (1) + KDF (1) + BLOCKEXP (1) + SALT (16) + NONCE PREFIX (8).
     */
    public const int V3_HEADER_LENGTH = 31 ;

    /**
     * Default block-size exponent written by `encrypt()`: 2^23 = 8 MiB.
     *
     * Large enough that the per-block 16-byte tag costs 0.0002% of the file, small
     * enough that the peak memory of a block plus its ciphertext stays in the tens of
     * mebibytes on the smallest VM.
     */
    public const int DEFAULT_BLOCK_SIZE_EXPONENT = 23 ;

    /**
     * Smallest block-size exponent accepted, on write and on read: 2^16 = 64 KiB.
     *
     * A hostile file could otherwise declare a 1-byte block size and turn a modest
     * payload into millions of tag verifications.
     */
    public const int MIN_BLOCK_SIZE_EXPONENT = 16 ;

    /**
     * Largest block-size exponent accepted, on write and on read: 2^30 = 1 GiB.
     *
     * Bounding the exponent **at read time** is what stops a hostile file from asking
     * for a 2^255-byte allocation before a single byte has been authenticated.
     */
    public const int MAX_BLOCK_SIZE_EXPONENT = 30 ;

    /**
     * Peak memory, as a multiple of the file size, of the in-memory V1/V2 read path.
     *
     * Measured on 2026-10-24: a 300 MB V2 file peaked at 1.18 GB. `file_get_contents()`
     * holds the ciphertext, `substr()` copies it, `openssl_decrypt()` allocates the
     * plaintext, and the write holds it again.
     */
    public const int LEGACY_MEMORY_FACTOR = 4 ;
}
