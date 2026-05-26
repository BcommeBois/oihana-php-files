<?php

namespace tests\oihana\files\openssl;

use oihana\files\openssl\enums\EncryptionFormat;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function oihana\files\openssl\bestAvailableKdf;
use function oihana\files\openssl\deriveKey;

class DeriveKeyTest extends TestCase
{
    public function testReturnsKeyOfCorrectLength(): void
    {
        $salt = random_bytes( EncryptionFormat::SALT_LENGTH ) ;
        $key  = deriveKey( 'passphrase' , $salt , EncryptionFormat::KDF_PBKDF2_SHA256 ) ;
        $this->assertSame( EncryptionFormat::KEY_LENGTH , strlen( $key ) ) ;
    }

    public function testPbkdf2IsDeterministic(): void
    {
        $salt = str_repeat( "\x01" , EncryptionFormat::SALT_LENGTH ) ;
        $a = deriveKey( 'passphrase' , $salt , EncryptionFormat::KDF_PBKDF2_SHA256 ) ;
        $b = deriveKey( 'passphrase' , $salt , EncryptionFormat::KDF_PBKDF2_SHA256 ) ;
        $this->assertSame( $a , $b , 'Same passphrase + salt + algo must yield same key' ) ;
    }

    public function testDifferentSaltsProduceDifferentKeys(): void
    {
        $a = deriveKey( 'passphrase' , str_repeat( "\x01" , 16 ) , EncryptionFormat::KDF_PBKDF2_SHA256 ) ;
        $b = deriveKey( 'passphrase' , str_repeat( "\x02" , 16 ) , EncryptionFormat::KDF_PBKDF2_SHA256 ) ;
        $this->assertNotSame( $a , $b ) ;
    }

    public function testDifferentPassphrasesProduceDifferentKeys(): void
    {
        $salt = str_repeat( "\x01" , 16 ) ;
        $a = deriveKey( 'passA' , $salt , EncryptionFormat::KDF_PBKDF2_SHA256 ) ;
        $b = deriveKey( 'passB' , $salt , EncryptionFormat::KDF_PBKDF2_SHA256 ) ;
        $this->assertNotSame( $a , $b ) ;
    }

    public function testArgon2idIsDeterministic(): void
    {
        if ( !function_exists('sodium_crypto_pwhash') )
        {
            $this->markTestSkipped('sodium extension not available') ;
        }

        $salt = str_repeat( "\x01" , EncryptionFormat::SALT_LENGTH ) ;
        $a = deriveKey( 'passphrase' , $salt , EncryptionFormat::KDF_ARGON2ID ) ;
        $b = deriveKey( 'passphrase' , $salt , EncryptionFormat::KDF_ARGON2ID ) ;
        $this->assertSame( $a , $b ) ;
        $this->assertSame( EncryptionFormat::KEY_LENGTH , strlen( $a ) ) ;
    }

    public function testArgon2idDiffersFromPbkdf2(): void
    {
        if ( !function_exists('sodium_crypto_pwhash') )
        {
            $this->markTestSkipped('sodium extension not available') ;
        }

        $salt = str_repeat( "\x01" , EncryptionFormat::SALT_LENGTH ) ;
        $a = deriveKey( 'passphrase' , $salt , EncryptionFormat::KDF_ARGON2ID ) ;
        $b = deriveKey( 'passphrase' , $salt , EncryptionFormat::KDF_PBKDF2_SHA256 ) ;
        $this->assertNotSame( $a , $b , 'Different KDFs on same input must produce different keys' ) ;
    }

    public function testThrowsOnEmptyPassphrase(): void
    {
        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessage( 'passphrase must not be empty' ) ;
        deriveKey( '' , random_bytes( 16 ) , EncryptionFormat::KDF_PBKDF2_SHA256 ) ;
    }

    public function testThrowsOnSaltWithWrongLength(): void
    {
        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessage( 'salt must be exactly' ) ;
        deriveKey( 'passphrase' , 'short' , EncryptionFormat::KDF_PBKDF2_SHA256 ) ;
    }

    public function testThrowsOnUnknownAlgorithm(): void
    {
        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessage( 'unknown KDF algorithm' ) ;
        deriveKey( 'passphrase' , str_repeat( "\x01" , 16 ) , 99 ) ;
    }

    public function testBestAvailableKdfReturnsArgonWhenSodiumAvailable(): void
    {
        if ( !function_exists('sodium_crypto_pwhash') )
        {
            $this->markTestSkipped('sodium extension not available') ;
        }

        $this->assertSame( EncryptionFormat::KDF_ARGON2ID , bestAvailableKdf() ) ;
    }
}
