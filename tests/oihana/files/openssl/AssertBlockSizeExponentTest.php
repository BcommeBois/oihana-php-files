<?php

namespace tests\oihana\files\openssl;

use InvalidArgumentException;

use oihana\files\openssl\enums\EncryptionFormat;
use PHPUnit\Framework\TestCase;

use function oihana\files\openssl\assertBlockSizeExponent;

class AssertBlockSizeExponentTest extends TestCase
{
    public function testTheDefaultExponentIsAccepted(): void
    {
        $this->assertSame
        (
            EncryptionFormat::DEFAULT_BLOCK_SIZE_EXPONENT ,
            assertBlockSizeExponent( EncryptionFormat::DEFAULT_BLOCK_SIZE_EXPONENT )
        ) ;
    }

    /**
     * Both bounds are inclusive, and that is worth an assertion of its own: an
     * off-by-one here would refuse the smallest block size the tests themselves use.
     */
    public function testBothBoundsAreInclusive(): void
    {
        $this->assertSame
        (
            EncryptionFormat::MIN_BLOCK_SIZE_EXPONENT ,
            assertBlockSizeExponent( EncryptionFormat::MIN_BLOCK_SIZE_EXPONENT )
        ) ;

        $this->assertSame
        (
            EncryptionFormat::MAX_BLOCK_SIZE_EXPONENT ,
            assertBlockSizeExponent( EncryptionFormat::MAX_BLOCK_SIZE_EXPONENT )
        ) ;
    }

    public function testJustBelowTheFloorIsRefused(): void
    {
        $this->expectException( InvalidArgumentException::class ) ;
        assertBlockSizeExponent( EncryptionFormat::MIN_BLOCK_SIZE_EXPONENT - 1 ) ;
    }

    public function testJustAboveTheCeilingIsRefused(): void
    {
        $this->expectException( InvalidArgumentException::class ) ;
        assertBlockSizeExponent( EncryptionFormat::MAX_BLOCK_SIZE_EXPONENT + 1 ) ;
    }

    /**
     * The reason the ceiling exists: a crafted header asking for a 2^255-byte
     * allocation must be refused before anything is allocated.
     */
    public function testAByteSizedHostileExponentIsRefused(): void
    {
        $this->expectException( InvalidArgumentException::class ) ;
        assertBlockSizeExponent( 255 ) ;
    }

    /**
     * And the reason the floor exists: 1-byte blocks turn a modest file into millions
     * of tag verifications.
     */
    public function testZeroIsRefused(): void
    {
        $this->expectException( InvalidArgumentException::class ) ;
        assertBlockSizeExponent( 0 ) ;
    }

    public function testTheMessageNamesTheAcceptedRange(): void
    {
        $this->expectException( InvalidArgumentException::class ) ;
        $this->expectExceptionMessage( sprintf
        (
            'expected %d to %d' ,
            EncryptionFormat::MIN_BLOCK_SIZE_EXPONENT ,
            EncryptionFormat::MAX_BLOCK_SIZE_EXPONENT
        ) ) ;

        assertBlockSizeExponent( 7 ) ;
    }
}
