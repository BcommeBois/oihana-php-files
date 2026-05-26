<?php

namespace tests\oihana\files\openssl;

use PHPUnit\Framework\TestCase;

use function oihana\files\openssl\isAeadCipher;

class IsAeadCipherTest extends TestCase
{
    public function testGcmIsAead(): void
    {
        $this->assertTrue( isAeadCipher('aes-256-gcm') ) ;
        $this->assertTrue( isAeadCipher('aes-128-gcm') ) ;
        $this->assertTrue( isAeadCipher('AES-256-GCM') , 'case-insensitive' ) ;
    }

    public function testCcmIsAead(): void
    {
        $this->assertTrue( isAeadCipher('aes-256-ccm') ) ;
    }

    public function testOcbIsAead(): void
    {
        $this->assertTrue( isAeadCipher('aes-256-ocb') ) ;
    }

    public function testCbcIsNotAead(): void
    {
        $this->assertFalse( isAeadCipher('aes-256-cbc') ) ;
        $this->assertFalse( isAeadCipher('aes-128-cbc') ) ;
    }

    public function testEcbIsNotAead(): void
    {
        $this->assertFalse( isAeadCipher('aes-256-ecb') ) ;
    }

    public function testEmptyIsNotAead(): void
    {
        $this->assertFalse( isAeadCipher('') ) ;
    }
}
