<?php

namespace oihana\files\openssl\enums;

use oihana\reflect\traits\ConstantsTrait;

/**
 * On-disk file format constants for {@see \oihana\files\openssl\OpenSSLFileEncryption}.
 *
 * The V2 format is the authenticated, KDF-protected format produced by `encrypt()`.
 * Files without the magic header are decrypted as legacy V1 (CBC + raw passphrase as key).
 *
 * Layout of a V2 file:
 * ```
 * ┌─────────┬─────────┬─────────┬──────────┬──────────┬──────────────────────┐
 * │ MAGIC   │ VERSION │ KDF     │ SALT     │ IV       │ ciphertext + TAG     │
 * │ 4 bytes │ 1 byte  │ 1 byte  │ 16 bytes │ 12 bytes │ variable + 16 bytes  │
 * └─────────┴─────────┴─────────┴──────────┴──────────┴──────────────────────┘
 * ```
 *
 * The KDF byte indicates how the encryption key was derived from the passphrase.
 * Storing it in the file lets a different environment decrypt the file — the
 * receiver does not need the same KDF implementation available, only the one
 * that was used at encryption time.
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
     * Current on-disk format version, written right after the magic.
     */
    public const int VERSION_V2 = 2 ;

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
}
