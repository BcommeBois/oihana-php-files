<?php

namespace oihana\files\openssl ;

use InvalidArgumentException;

use oihana\files\openssl\enums\EncryptionFormat;

/**
 * Builds the GCM nonce of one V3 block: the file's random prefix followed by the
 * block index.
 *
 * ```
 * nonce(i) = NONCE PREFIX (8 bytes) ‖ pack('N', i) (4 bytes) = 12 bytes
 * ```
 *
 * **Why a counter and not 12 random bytes per block.** A nonce must never repeat
 * under one key, and with AES-GCM a repeat is not a degradation but a collapse: two
 * blocks sharing a nonce leak their XOR and, worse, hand an attacker the material to
 * forge tags for *any* message under that key. Random 96-bit nonces collide by
 * birthday after about 2^32 blocks; a counter cannot collide at all within a file,
 * and the per-file random prefix keeps two files apart.
 *
 * Which is why the index is bounded here. {@see EncryptionFormat::BLOCK_COUNTER_LENGTH}
 * is 4 bytes, and `pack('N', 2**32)` wraps silently back to zero — the one arithmetic
 * accident in this format that would reuse a nonce. At the default block size that
 * ceiling sits at 32 EiB, so the guard costs nothing and removes the accident.
 *
 * @param string $noncePrefix The file's random prefix, exactly
 *                            {@see EncryptionFormat::NONCE_PREFIX_LENGTH} bytes.
 * @param int    $index       The zero-based block index.
 *
 * @return string The {@see EncryptionFormat::GCM_IV_LENGTH}-byte nonce of that block.
 *
 * @throws InvalidArgumentException If the prefix has the wrong length, or the index is
 *                                 negative or past what the counter can hold.
 *
 * @example
 * ```php
 * use function oihana\files\openssl\encryptionBlockNonce;
 *
 * $prefix = random_bytes( 8 ) ;
 * encryptionBlockNonce( $prefix , 0 ) ; // $prefix . "\x00\x00\x00\x00"
 * encryptionBlockNonce( $prefix , 1 ) ; // $prefix . "\x00\x00\x00\x01"
 * ```
 *
 * @package oihana\files\openssl
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.5.0
 */
function encryptionBlockNonce( string $noncePrefix , int $index ): string
{
    if ( strlen( $noncePrefix ) !== EncryptionFormat::NONCE_PREFIX_LENGTH )
    {
        throw new InvalidArgumentException( sprintf
        (
            'Nonce prefix must be exactly %d bytes, %d given.' ,
            EncryptionFormat::NONCE_PREFIX_LENGTH ,
            strlen( $noncePrefix )
        )) ;
    }

    $maxIndex = ( 1 << ( EncryptionFormat::BLOCK_COUNTER_LENGTH * 8 ) ) - 1 ;

    if ( $index < 0 || $index > $maxIndex )
    {
        throw new InvalidArgumentException( sprintf
        (
            'Block index %d is out of range: expected 0 to %d, past which the %d-byte counter would wrap and reuse a nonce.' ,
            $index ,
            $maxIndex ,
            EncryptionFormat::BLOCK_COUNTER_LENGTH
        )) ;
    }

    return $noncePrefix . pack( 'N' , $index ) ;
}
