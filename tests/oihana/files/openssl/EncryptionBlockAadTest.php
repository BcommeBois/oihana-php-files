<?php

namespace tests\oihana\files\openssl;

use InvalidArgumentException;

use oihana\files\openssl\enums\EncryptionFormat;
use PHPUnit\Framework\TestCase;

use function oihana\files\openssl\encryptionBlockAad;

class EncryptionBlockAadTest extends TestCase
{
    private string $header ;

    protected function setUp(): void
    {
        $this->header = str_repeat( "\x2a" , EncryptionFormat::V3_HEADER_LENGTH ) ;
    }

    public function testLayoutIsHeaderThenIndexThenFlag(): void
    {
        $aad = encryptionBlockAad( $this->header , 5 , true ) ;

        $this->assertSame
        (
            EncryptionFormat::V3_HEADER_LENGTH + EncryptionFormat::BLOCK_COUNTER_LENGTH + 1 ,
            strlen( $aad )
        ) ;

        $this->assertSame( $this->header . "\x00\x00\x00\x05" . "\x01" , $aad ) ;
    }

    public function testTheFinalFlagIsAZeroByteWhenMoreBlocksFollow(): void
    {
        $this->assertSame( $this->header . "\x00\x00\x00\x00" . "\x00" , encryptionBlockAad( $this->header , 0 , false ) ) ;
    }

    /**
     * What stops a truncation: the reader decides *final* from the cursor, so a frame
     * sealed as non-final and read as the last one is opened against a different AAD.
     */
    public function testTheFinalFlagChangesTheAssociatedData(): void
    {
        $this->assertNotSame
        (
            encryptionBlockAad( $this->header , 3 , false ) ,
            encryptionBlockAad( $this->header , 3 , true  )
        ) ;
    }

    /**
     * What stops two frames being swapped, or one being dropped from the middle.
     */
    public function testEachPositionGetsItsOwnAssociatedData(): void
    {
        $aads = [] ;

        for ( $i = 0 ; $i < 64 ; $i++ )
        {
            $aads[] = encryptionBlockAad( $this->header , $i , false ) ;
        }

        $this->assertCount( 64 , array_unique( $aads ) ) ;
    }

    /**
     * What stops two files encrypted with the same passphrase being spliced together:
     * the header carries a per-file salt and nonce prefix.
     */
    public function testTwoFilesNeverShareAssociatedData(): void
    {
        $other = str_repeat( "\x2b" , EncryptionFormat::V3_HEADER_LENGTH ) ;

        $this->assertNotSame
        (
            encryptionBlockAad( $this->header , 0 , true ) ,
            encryptionBlockAad( $other , 0 , true )
        ) ;
    }

    public function testTooShortAHeaderIsRefused(): void
    {
        $this->expectException( InvalidArgumentException::class ) ;
        $this->expectExceptionMessage( 'A V3 header is exactly' ) ;

        encryptionBlockAad( substr( $this->header , 0 , -1 ) , 0 , false ) ;
    }

    public function testTooLongAHeaderIsRefused(): void
    {
        $this->expectException( InvalidArgumentException::class ) ;
        encryptionBlockAad( $this->header . "\x00" , 0 , false ) ;
    }

    public function testNegativeIndexIsRefused(): void
    {
        $this->expectException( InvalidArgumentException::class ) ;
        encryptionBlockAad( $this->header , -1 , false ) ;
    }

    public function testTheLastIndexTheCounterHoldsIsAccepted(): void
    {
        $max = ( 1 << ( EncryptionFormat::BLOCK_COUNTER_LENGTH * 8 ) ) - 1 ;

        $this->assertStringEndsWith( "\xff\xff\xff\xff" . "\x00" , encryptionBlockAad( $this->header , $max , false ) ) ;
    }

    public function testAnIndexPastTheCounterIsRefused(): void
    {
        $this->expectException( InvalidArgumentException::class ) ;
        $this->expectExceptionMessage( 'is out of range' ) ;

        encryptionBlockAad( $this->header , 1 << ( EncryptionFormat::BLOCK_COUNTER_LENGTH * 8 ) , false ) ;
    }
}
