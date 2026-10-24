<?php

namespace oihana\files\openssl ;

use oihana\files\openssl\enums\EncryptionFormat;

/**
 * Tells whether the KDF a file names can actually be run here.
 *
 * Every encrypted file stores the identifier of the KDF its key was derived with, so
 * a reader never has to guess. What that byte cannot do is make the KDF *available*:
 * {@see EncryptionFormat::KDF_ARGON2ID} needs `ext-sodium` at decryption time exactly
 * as much as at encryption time, and `ext-sodium` is a suggestion of this package, not
 * a requirement.
 *
 * Which produces the one case worth a function of its own: a file written on a host
 * that has sodium, read on a host that does not. Measured on 2026-10-24, that read
 * failed with *"Decryption failed due to incorrect passphrase or corrupted data"* — on
 * an intact archive, with the right passphrase, because the real cause sat in a
 * wrapped exception nobody prints. For someone restoring a backup, that message sends
 * them looking for a lost passphrase or a corrupt file instead of a missing extension.
 *
 * {@see EncryptionFormat::KDF_PBKDF2_SHA256} is native to PHP, so it is available
 * everywhere.
 *
 * **An unknown identifier answers `true`**, and that is deliberate: it names no
 * implementation that could be missing. A byte that is neither of the two known
 * values is a corrupt or tampered header, or one from a newer release — all of which
 * {@see deriveKey()} must reject, and under the generic message, since a forged byte
 * must not get an answer of its own.
 *
 * @param bool|null $sodiumLoaded Whether `ext-sodium` is loaded. Testing seam; `null`
 *                                asks `function_exists()`.
 *
 * @example
 * ```php
 * use function oihana\files\openssl\kdfIsAvailable;
 * use oihana\files\openssl\enums\EncryptionFormat;
 *
 * kdfIsAvailable( EncryptionFormat::KDF_ARGON2ID ) ;             // false without ext-sodium
 * kdfIsAvailable( EncryptionFormat::KDF_PBKDF2_SHA256 ) ;        // always true
 * kdfIsAvailable( EncryptionFormat::KDF_ARGON2ID , false ) ;     // false
 * ```
 *
 * @package oihana\files\openssl
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.5.0
 */
function kdfIsAvailable( int $algorithm , ?bool $sodiumLoaded = null ): bool
{
    return match ( $algorithm )
    {
        EncryptionFormat::KDF_ARGON2ID => $sodiumLoaded ?? function_exists('sodium_crypto_pwhash') ,
        default                        => true
    } ;
}
