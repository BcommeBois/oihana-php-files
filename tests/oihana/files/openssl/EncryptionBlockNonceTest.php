<?php

namespace tests\oihana\files\openssl;

use InvalidArgumentException;

use oihana\files\openssl\enums\EncryptionFormat;
use PHPUnit\Framework\TestCase;

use function oihana\files\openssl\encryptionBlockNonce;

class EncryptionBlockNonceTest extends TestCase
{
    private string $prefix ;

    protected function setUp(): void
    {
        $this->prefix = str_repeat( "\x7f" , EncryptionFormat::NONCE_PREFIX_LENGTH ) ;
    }

    /**
     * The format invariant the nonce rests on: prefix plus counter must be exactly
     * what GCM takes as an IV. Break either constant and this fails here rather than
     * in a file nobody can read any more.
     */
    public function testPrefixAndCounterFillTheGcmIv(): void
    {
        $this->assertSame
        (
            EncryptionFormat::GCM_IV_LENGTH ,
            EncryptionFormat::NONCE_PREFIX_LENGTH + EncryptionFormat::BLOCK_COUNTER_LENGTH
        ) ;

        $this->assertSame( EncryptionFormat::GCM_IV_LENGTH , strlen( encryptionBlockNonce( $this->prefix , 0 ) ) ) ;
    }

    public function testFirstBlockCounterIsZero(): void
    {
        $this->assertSame( $this->prefix . "\x00\x00\x00\x00" , encryptionBlockNonce( $this->prefix , 0 ) ) ;
    }

    public function testCounterIsBigEndian(): void
    {
        $this->assertSame( $this->prefix . "\x00\x00\x01\x00" , encryptionBlockNonce( $this->prefix , 256 ) ) ;
    }

    public function testEveryBlockOfAFileGetsItsOwnNonce(): void
    {
        $nonces = [] ;

        for ( $i = 0 ; $i < 64 ; $i++ )
        {
            $nonces[] = encryptionBlockNonce( $this->prefix , $i ) ;
        }

        $this->assertCount( 64 , array_unique( $nonces ) ) ;
    }

    public function testTwoFilesWithDifferentPrefixesNeverShareANonce(): void
    {
        $other = str_repeat( "\x01" , EncryptionFormat::NONCE_PREFIX_LENGTH ) ;

        $this->assertNotSame
        (
            encryptionBlockNonce( $this->prefix , 3 ) ,
            encryptionBlockNonce( $other , 3 )
        ) ;
    }

    public function testTooShortAPrefixIsRefused(): void
    {
        $this->expectException( InvalidArgumentException::class ) ;
        encryptionBlockNonce( str_repeat( "\x00" , EncryptionFormat::NONCE_PREFIX_LENGTH - 1 ) , 0 ) ;
    }

    public function testTooLongAPrefixIsRefused(): void
    {
        $this->expectException( InvalidArgumentException::class ) ;
        $this->expectExceptionMessage( 'Nonce prefix must be exactly' ) ;
        encryptionBlockNonce( str_repeat( "\x00" , EncryptionFormat::GCM_IV_LENGTH ) , 0 ) ;
    }

    public function testNegativeIndexIsRefused(): void
    {
        $this->expectException( InvalidArgumentException::class ) ;
        encryptionBlockNonce( $this->prefix , -1 ) ;
    }

    public function testTheLastIndexTheCounterHoldsIsAccepted(): void
    {
        $max = ( 1 << ( EncryptionFormat::BLOCK_COUNTER_LENGTH * 8 ) ) - 1 ;

        $this->assertSame( $this->prefix . "\xff\xff\xff\xff" , encryptionBlockNonce( $this->prefix , $max ) ) ;
    }

    /**
     * One past the counter, where `pack('N', …)` wraps back to zero and quietly hands
     * a second block the nonce of the first.
     */
    public function testAnIndexThatWouldWrapTheCounterIsRefused(): void
    {
        $this->expectException( InvalidArgumentException::class ) ;
        $this->expectExceptionMessage( 'would wrap and reuse a nonce' ) ;

        encryptionBlockNonce( $this->prefix , 1 << ( EncryptionFormat::BLOCK_COUNTER_LENGTH * 8 ) ) ;
    }
}
