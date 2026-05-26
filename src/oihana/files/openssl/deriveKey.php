<?php

namespace oihana\files\openssl ;

use RuntimeException;

use oihana\files\openssl\enums\EncryptionFormat;

/**
 * Derives a 32-byte symmetric encryption key from a passphrase using a slow KDF.
 *
 * **Why a KDF ?** A passphrase like `'chocolat'` has roughly 8 bytes of entropy.
 * Using it directly as an AES-256 key (zero-padded to 32 bytes) makes brute-force
 * trivial. A KDF deliberately slows the key derivation down (hundreds of
 * thousands of iterations or memory-hard computation) so that an attacker who
 * tries 1 million passphrases must do 1 million × N work — increasing brute-force
 * cost by orders of magnitude.
 *
 * **Supported algorithms** (passed via `$algorithm`):
 * - {@see EncryptionFormat::KDF_ARGON2ID} — memory-hard, GPU-resistant.
 *   Requires the `sodium` extension. Recommended when available.
 * - {@see EncryptionFormat::KDF_PBKDF2_SHA256} — universal fallback, 600 000
 *   iterations of SHA-256 (OWASP 2023+).
 *
 * Use {@see bestAvailableKdf()} to pick the strongest algorithm available in
 * the current PHP environment.
 *
 * The chosen algorithm is **explicit** so that the encrypted file can carry it
 * and any reader (even on a different PHP build) can reproduce the derivation.
 *
 * **Security boundary:** this function protects the *cost* of brute-forcing the
 * passphrase. It does **not** protect against:
 * - a leaked salt + ciphertext + passphrase (anyone with all three can decrypt) ;
 * - a passphrase observable by an attacker (e.g. typed in front of a camera) ;
 * - side-channel attacks on the host (memory dump while passphrase is in RAM).
 *
 * @param string $passphrase The user-provided passphrase. Must be non-empty.
 * @param string $salt       A per-file random salt of {@see EncryptionFormat::SALT_LENGTH} bytes.
 * @param int    $algorithm  One of {@see EncryptionFormat::KDF_ARGON2ID} or
 *                           {@see EncryptionFormat::KDF_PBKDF2_SHA256}.
 *
 * @return string A 32-byte raw binary key.
 *
 * @throws RuntimeException If the passphrase is empty, the salt has wrong length,
 *                          the algorithm is unknown, or Argon2id is requested but
 *                          the sodium extension is not loaded.
 *
 * @example
 * ```php
 * use function oihana\files\openssl\{ deriveKey , bestAvailableKdf } ;
 * use oihana\files\openssl\enums\EncryptionFormat;
 *
 * $salt = random_bytes( EncryptionFormat::SALT_LENGTH ) ;
 * $algo = bestAvailableKdf() ;
 * $key  = deriveKey( 'my passphrase' , $salt , $algo ) ;
 * ```
 *
 * @package oihana\files\openssl
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
function deriveKey( string $passphrase , string $salt , int $algorithm ): string
{
    if ( $passphrase === '' )
    {
        throw new RuntimeException('deriveKey: passphrase must not be empty.') ;
    }

    if ( strlen( $salt ) !== EncryptionFormat::SALT_LENGTH )
    {
        throw new RuntimeException( sprintf
        (
            'deriveKey: salt must be exactly %d bytes, got %d.' ,
            EncryptionFormat::SALT_LENGTH ,
            strlen( $salt )
        )) ;
    }

    return match ( $algorithm )
    {
        EncryptionFormat::KDF_ARGON2ID => (function () use ( $passphrase , $salt ) : string
        {
            if ( !function_exists('sodium_crypto_pwhash' ) )
            {
                throw new RuntimeException
                (
                    'deriveKey: Argon2id requested but the sodium extension is not loaded.'
                ) ;
            }
            return sodium_crypto_pwhash
            (
                EncryptionFormat::KEY_LENGTH ,
                $passphrase ,
                $salt ,
                SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE ,
                SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE ,
                SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
            ) ;
        })() ,

        EncryptionFormat::KDF_PBKDF2_SHA256 => hash_pbkdf2
        (
            'sha256' ,
            $passphrase ,
            $salt ,
            EncryptionFormat::PBKDF2_ITERATIONS ,
            EncryptionFormat::KEY_LENGTH ,
            true
        ) ,

        default => throw new RuntimeException( sprintf
        (
            'deriveKey: unknown KDF algorithm identifier %d.' ,
            $algorithm
        ))
    } ;
}

/**
 * Returns the strongest KDF algorithm available in the current PHP environment.
 *
 * - If `ext-sodium` is loaded → {@see EncryptionFormat::KDF_ARGON2ID}.
 * - Otherwise → {@see EncryptionFormat::KDF_PBKDF2_SHA256}.
 *
 * Use this when writing new encrypted files; for reading, the algorithm is
 * dictated by the file header.
 *
 * @return int One of {@see EncryptionFormat::KDF_ARGON2ID} or
 *             {@see EncryptionFormat::KDF_PBKDF2_SHA256}.
 *
 * @example
 * ```php
 * use function oihana\files\openssl\bestAvailableKdf;
 *
 * $kdf = bestAvailableKdf() ;
 * // → KDF_ARGON2ID on PHP 8.4 with sodium (the usual case)
 * // → KDF_PBKDF2_SHA256 on stripped-down builds
 * ```
 *
 * @package oihana\files\openssl
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
function bestAvailableKdf(): int
{
    return function_exists('sodium_crypto_pwhash' )
        ? EncryptionFormat::KDF_ARGON2ID
        : EncryptionFormat::KDF_PBKDF2_SHA256 ;
}
