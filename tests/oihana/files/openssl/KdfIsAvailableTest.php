<?php

namespace tests\oihana\files\openssl;

use oihana\files\openssl\enums\EncryptionFormat;
use PHPUnit\Framework\TestCase;

use function oihana\files\openssl\kdfIsAvailable;

class KdfIsAvailableTest extends TestCase
{
    public function testArgon2idNeedsSodium(): void
    {
        $this->assertFalse( kdfIsAvailable( EncryptionFormat::KDF_ARGON2ID , false ) ) ;
        $this->assertTrue ( kdfIsAvailable( EncryptionFormat::KDF_ARGON2ID , true  ) ) ;
    }

    /**
     * PBKDF2-SHA256 is `hash_pbkdf2()`, native to PHP: a file written with it reads on
     * any host, sodium or not.
     */
    public function testPbkdf2IsAvailableEvenWithoutSodium(): void
    {
        $this->assertTrue( kdfIsAvailable( EncryptionFormat::KDF_PBKDF2_SHA256 , false ) ) ;
    }

    /**
     * An unknown identifier names no implementation that could be missing, so it is
     * not reported as unavailable: it is a corrupt, forged or newer header, and
     * `deriveKey()` rejects it under the generic message rather than telling a forger
     * which byte they got wrong.
     */
    public function testAnUnknownIdentifierIsNotReportedAsAMissingExtension(): void
    {
        $this->assertTrue( kdfIsAvailable( 99 , false ) ) ;
        $this->assertTrue( kdfIsAvailable( 99 , true  ) ) ;
        $this->assertTrue( kdfIsAvailable( 0  , false ) ) ;
    }

    /**
     * The default argument, asserted against the environment the suite runs in rather
     * than against an assumption about it.
     */
    public function testDefaultsToTheLoadedExtensions(): void
    {
        $this->assertSame
        (
            function_exists('sodium_crypto_pwhash') ,
            kdfIsAvailable( EncryptionFormat::KDF_ARGON2ID )
        ) ;
    }
}
