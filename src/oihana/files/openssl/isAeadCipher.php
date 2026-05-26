<?php

namespace oihana\files\openssl ;

/**
 * Determines whether an OpenSSL cipher name designates an AEAD mode.
 *
 * AEAD (Authenticated Encryption with Associated Data) ciphers compute an
 * authentication tag in addition to the ciphertext. PHP's `openssl_encrypt()`
 * / `openssl_decrypt()` API requires the `&$tag` parameter for these ciphers;
 * without it, integrity is not protected.
 *
 * Currently recognised modes:
 * - **GCM** (Galois/Counter Mode) — preferred for general use.
 * - **CCM** (Counter with CBC-MAC).
 * - **OCB** (Offset Codebook) — modern, less ubiquitous.
 *
 * The check is purely textual on the cipher name (case-insensitive).
 *
 * @param string $cipher Cipher name as returned by `openssl_get_cipher_methods()`,
 *                       e.g. `'aes-256-gcm'`, `'aes-128-ccm'`, `'aes-256-cbc'`.
 *
 * @return bool True if the cipher operates in an AEAD mode.
 *
 * @example
 * ```php
 * use function oihana\files\openssl\isAeadCipher;
 *
 * isAeadCipher('aes-256-gcm') ;  // true
 * isAeadCipher('aes-128-ccm') ;  // true
 * isAeadCipher('aes-256-cbc') ;  // false
 * isAeadCipher('chacha20-poly1305') ; // false — handled separately by sodium
 * ```
 *
 * @package oihana\files\openssl
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
function isAeadCipher( string $cipher ): bool
{
    $cipher = strtolower( $cipher ) ;
    return str_contains( $cipher , '-gcm' )
        || str_contains( $cipher , '-ccm' )
        || str_contains( $cipher , '-ocb' ) ;
}
