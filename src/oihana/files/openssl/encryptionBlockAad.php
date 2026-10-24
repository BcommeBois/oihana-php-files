<?php

namespace oihana\files\openssl ;

use InvalidArgumentException;

use oihana\files\openssl\enums\EncryptionFormat;

/**
 * Builds the associated data that binds one V3 block to its file, its position and
 * the end of the stream.
 *
 * ```
 * AAD(i) = header (31 bytes) ‖ pack('N', i) (4 bytes) ‖ chr( isFinal ? 1 : 0 ) (1 byte)
 * ```
 *
 * **This function is the integrity of the format.** A GCM tag proves that *a* block
 * was sealed with the key — nothing more. Seal each block with no associated data and
 * every frame authenticates on its own, which leaves four rewrites of a file
 * undetectable, all of them trivial to perform with no key at all:
 *
 * | Attack | What stops it |
 * |---|---|
 * | Cut the file short | the final flag: the reader computes *final* from the cursor, so the new last frame was sealed as non-final and fails |
 * | Swap two frames | the block index |
 * | Drop a frame from the middle | the block index of every frame after it |
 * | Splice two files from the same passphrase | the header, whose salt and nonce prefix are per-file |
 *
 * The flag is one byte rather than a bit of the index because it has to be *decided*
 * on the read side, from the cursor position against the file size, before the block
 * is opened. Writer and reader must agree on it, and that agreement is what a
 * truncation breaks.
 *
 * @param string $header  The file's V3 header, exactly {@see EncryptionFormat::V3_HEADER_LENGTH} bytes.
 * @param int    $index   The zero-based block index.
 * @param bool   $isFinal Whether this block is the last of the file.
 *
 * @return string The associated data to pass to `openssl_encrypt()` / `openssl_decrypt()`.
 *
 * @throws InvalidArgumentException If the header has the wrong length, or the index is
 *                                 negative or past what the counter can hold.
 *
 * @example
 * ```php
 * use function oihana\files\openssl\encryptionBlockAad;
 *
 * $aad = encryptionBlockAad( $header , 0 , false ) ; // first block of several
 * $end = encryptionBlockAad( $header , 7 , true  ) ; // the one that closes the file
 * ```
 *
 * @package oihana\files\openssl
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.5.0
 */
function encryptionBlockAad( string $header , int $index , bool $isFinal ): string
{
    if ( strlen( $header ) !== EncryptionFormat::V3_HEADER_LENGTH )
    {
        throw new InvalidArgumentException( sprintf
        (
            'A V3 header is exactly %d bytes, %d given.' ,
            EncryptionFormat::V3_HEADER_LENGTH ,
            strlen( $header )
        )) ;
    }

    $maxIndex = ( 1 << ( EncryptionFormat::BLOCK_COUNTER_LENGTH * 8 ) ) - 1 ;

    if ( $index < 0 || $index > $maxIndex )
    {
        throw new InvalidArgumentException( sprintf
        (
            'Block index %d is out of range: expected 0 to %d.' ,
            $index ,
            $maxIndex
        )) ;
    }

    return $header . pack( 'N' , $index ) . chr( $isFinal ? 1 : 0 ) ;
}
