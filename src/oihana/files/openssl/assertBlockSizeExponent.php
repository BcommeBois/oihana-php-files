<?php

namespace oihana\files\openssl ;

use InvalidArgumentException;

use oihana\files\openssl\enums\EncryptionFormat;

/**
 * Asserts that a V3 block-size exponent is one this library will act on, and returns it.
 *
 * The exponent is a single byte of the V3 header, so a file can declare any value from
 * 0 to 255 — and the reader meets it **before a single byte has been authenticated**.
 * Both ends of the range are hostile:
 *
 * - **Too small.** An exponent of 0 asks for 1-byte blocks, which turns a 100 MB file
 *   into a hundred million frames, each with its own 16-byte tag and its own
 *   `openssl_decrypt()` call. The file would be 1.6 GB of tags and the read would run
 *   for hours. {@see EncryptionFormat::MIN_BLOCK_SIZE_EXPONENT} (64 KiB) is the floor.
 * - **Too large.** An exponent of 255 asks the reader to allocate 2^255 bytes for the
 *   first block. {@see EncryptionFormat::MAX_BLOCK_SIZE_EXPONENT} (1 GiB) is the
 *   ceiling, and `1 << 255` is undefined ground this never reaches.
 *
 * Called on both sides: on write so the library never produces a file it would refuse
 * to read, and on read so a crafted header is refused before it costs anything.
 *
 * @param int $exponent The block-size exponent, as read from a header or chosen for one.
 *
 * @return int The same exponent, so it can be validated in place: `1 << assertBlockSizeExponent( $e )`.
 *
 * @throws InvalidArgumentException If the exponent falls outside the accepted range.
 *
 * @example
 * ```php
 * use function oihana\files\openssl\assertBlockSizeExponent;
 *
 * $blockSize = 1 << assertBlockSizeExponent( 23 ) ; // 8388608
 * assertBlockSizeExponent( 255 ) ;                  // InvalidArgumentException
 * ```
 *
 * @package oihana\files\openssl
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.5.0
 */
function assertBlockSizeExponent( int $exponent ): int
{
    if
    (
        $exponent < EncryptionFormat::MIN_BLOCK_SIZE_EXPONENT ||
        $exponent > EncryptionFormat::MAX_BLOCK_SIZE_EXPONENT
    )
    {
        throw new InvalidArgumentException( sprintf
        (
            'Block size exponent %d is out of range: expected %d to %d (2^%d to 2^%d bytes).' ,
            $exponent ,
            EncryptionFormat::MIN_BLOCK_SIZE_EXPONENT ,
            EncryptionFormat::MAX_BLOCK_SIZE_EXPONENT ,
            EncryptionFormat::MIN_BLOCK_SIZE_EXPONENT ,
            EncryptionFormat::MAX_BLOCK_SIZE_EXPONENT
        )) ;
    }

    return $exponent ;
}
