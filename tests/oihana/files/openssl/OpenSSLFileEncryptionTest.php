<?php

namespace tests\oihana\files\openssl;

use oihana\files\exceptions\DirectoryException;
use oihana\files\exceptions\FileException;
use oihana\files\openssl\OpenSSLFileEncryption;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class OpenSSLFileEncryptionTest extends TestCase
{
    private string $testDir;
    private string $inputFile;
    private string $encryptedFile;
    private string $decryptedFile;
    private string $passphrase;
    private string $cipher;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/OpenSSLFileEncryptionTest' ;

        if ( !file_exists($this->testDir) )
        {
            mkdir( $this->testDir, 0777, true );
        }

        $this->inputFile     = $this->testDir . '/input.txt';
        $this->encryptedFile = $this->testDir . '/encrypted.txt.enc';
        $this->decryptedFile = $this->testDir . '/decrypted.txt';
        $this->passphrase    = 'secret';
        $this->cipher        = 'aes-256-cbc';

        file_put_contents( $this->inputFile , 'Hello, World!' ) ;
    }

    protected function tearDown(): void
    {
        if ( file_exists($this->inputFile) )
        {
            unlink($this->inputFile);
        }

        if (file_exists($this->encryptedFile))
        {
            unlink($this->encryptedFile);
        }

        if (file_exists($this->decryptedFile))
        {
            unlink($this->decryptedFile);
        }

        // ---- Delete any subdirectories created during testing.
        if (file_exists($this->testDir)) {
            $items = array_diff(scandir($this->testDir), ['.', '..']);
            foreach ($items as $item) {
                $path = $this->testDir . '/' . $item;
                if (is_file($path)) {
                    unlink($path);
                }
            }
            rmdir($this->testDir);
        }
    }

    public function testConstruct()
    {
        new OpenSSLFileEncryption($this->passphrase, $this->cipher);
        $this->assertTrue(true ) ; // Placeholder assertion
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testEncryptAndDecrypt()
    {
        $encryption = new OpenSSLFileEncryption($this->passphrase, $this->cipher);

        // Test encryption
        $result = $encryption->encrypt($this->inputFile, $this->encryptedFile);
        $this->assertEquals( $this->encryptedFile , $result );
        $this->assertFileExists($this->encryptedFile);

        // Test decryption
        $result = $encryption->decrypt($this->encryptedFile, $this->decryptedFile);
        $this->assertEquals( $this->decryptedFile , $result);
        $this->assertFileExists($this->decryptedFile);

        // Verify that the decrypted file matches the original
        $originalContent = file_get_contents($this->inputFile);
        $decryptedContent = file_get_contents($this->decryptedFile);
        $this->assertEquals($originalContent, $decryptedContent);
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testEncryptWithNonExistentInputFile()
    {
        $encryption = new OpenSSLFileEncryption($this->passphrase, $this->cipher);
        $nonExistentFile = $this->testDir . '/nonexistent.txt';

        $this->expectException(FileException::class);
        $this->expectExceptionMessage( sprintf('The file path "%s" is not a valid file.' , $nonExistentFile ) );

        $encryption->encrypt($nonExistentFile, $this->encryptedFile);
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testEncryptWithUnwritableOutputFile()
    {
       $encryption = new OpenSSLFileEncryption($this->passphrase, $this->cipher);

        // Create a temporary subdirectory without write permissions
        $outputFile = $this->testDir . '/encrypted.txt';

        // Creates an empty output file
        file_put_contents($outputFile, '' ) ;
        // The output file is readonly
        chmod($outputFile, 0444) ;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches( "/Encryption failed/" );

        $encryption->encrypt($this->inputFile, $outputFile);

        // Nettoyer
        if (file_exists($outputFile))
        {
            chmod($outputFile, 0644 ) ; // Restore the permissions to unlink the file
            unlink($outputFile);
        }
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testEncryptWithNonExistentOutputDirectory()
    {
        $encryption = new OpenSSLFileEncryption($this->passphrase, $this->cipher);

        $nonExistentDir = $this->testDir . '/nonexistent_directory';
        $nonExistentFile = $nonExistentDir . '/encrypted.txt';

        $this->expectException(DirectoryException::class);
        $this->expectExceptionMessageMatches('/is not a valid directory/');
        $encryption->encrypt($this->inputFile, $nonExistentFile);
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testDecryptWithIncorrectPassphrase()
    {
        $encryption = new OpenSSLFileEncryption($this->passphrase, $this->cipher);
        $encryption->encrypt($this->inputFile, $this->encryptedFile);

        $wrongPassphrase = 'wrongpassphrase';
        $encryptionWithWrongPassphrase = new OpenSSLFileEncryption($wrongPassphrase, $this->cipher);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Decryption failed due to incorrect passphrase or corrupted data.");
        $encryptionWithWrongPassphrase->decrypt($this->encryptedFile, $this->decryptedFile);
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testDecryptWithUnwritableOutputFile()
    {
        $encryption = new OpenSSLFileEncryption($this->passphrase, $this->cipher);
        $encryption->encrypt($this->inputFile, $this->encryptedFile);

        $unwritableDir = '/unwritable/directory';
        $unwritableFile = $unwritableDir . '/decrypted.txt';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Decryption failed, output directory does not exist.");
        $encryption->decrypt($this->encryptedFile, $unwritableFile);
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testHasEncryptedFileSize()
    {
        $encryption = new OpenSSLFileEncryption($this->passphrase, $this->cipher);

        $plainFile = $this->testDir . '/plain.txt';
        file_put_contents($plainFile, 'This is plain text data');

        $encryptedFile = $this->testDir . '/encrypted.txt';
        $encryption->encrypt($this->inputFile, $encryptedFile);

        $emptyFile = $this->testDir . '/empty.txt';
        file_put_contents($emptyFile, '');

        $shortFile = $this->testDir . '/short.txt';
        file_put_contents($shortFile, str_repeat('a', $encryption->ivLength - 1)); // 1 octet de moins que l'IV

        $nonExistentFile = $this->testDir . '/nonexistent.txt';

        $ivSizeFile = $this->testDir . '/ivsize.txt';
        file_put_contents($ivSizeFile, str_repeat('a', $encryption->ivLength ));

        $this->assertTrue ( $encryption->hasEncryptedFileSize( $encryptedFile   ) , 'Should detect valid encrypted file size' ) ;
        $this->assertTrue ( $encryption->hasEncryptedFileSize( $plainFile       ) , 'File with sufficient size returns true' ) ;
        $this->assertFalse( $encryption->hasEncryptedFileSize( $emptyFile       ) , 'Empty file returns false' ) ;
        $this->assertFalse( $encryption->hasEncryptedFileSize( $shortFile       ) , 'File shorter than IV returns false' ) ;
        $this->assertFalse( $encryption->hasEncryptedFileSize( $nonExistentFile ) , 'Non-existent file returns false' ) ;
        $this->assertTrue ( $encryption->hasEncryptedFileSize( $ivSizeFile      ) , 'File exactly IV length returns true' ) ;

        foreach ([ $plainFile, $encryptedFile, $emptyFile, $shortFile, $ivSizeFile ] as $file)
        {
            if ( file_exists( $file ) )
            {
                unlink( $file );
            }
        }
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testIsEncryptedFile()
    {
        $encryption = new OpenSSLFileEncryption($this->passphrase, $this->cipher);

        $plainFile = $this->testDir . '/plain.txt';
        file_put_contents($plainFile, 'This is plain text data');

        $encryptedFile = $this->testDir . '/encrypted.txt';
        $encryption->encrypt( $this->inputFile, $encryptedFile);

        $emptyFile = $this->testDir . '/empty.txt';
        file_put_contents($emptyFile, '');

        $shortFile = $this->testDir . '/short.txt';
        file_put_contents($shortFile, 'short');

        $nonExistentFile = $this->testDir . '/nonexistent.txt';

        $corruptedFile = $this->testDir . '/corrupted.txt';
        $data = file_get_contents($encryptedFile);
        file_put_contents($corruptedFile, substr($data, 0, 10));

        $ivSizeFile = $this->testDir . '/ivsize.txt';
        file_put_contents($ivSizeFile, str_repeat("\0", $encryption->ivLength )); // IV rempli de zéros

        $this->assertTrue ( $encryption->isEncryptedFile( $encryptedFile ), 'Should detect valid encrypted file' ) ;
        $this->assertFalse( $encryption->isEncryptedFile( $plainFile ), 'Should not detect plain text file as encrypted' ) ;
        $this->assertFalse( $encryption->isEncryptedFile( $emptyFile ), 'Should not detect empty file as encrypted' ) ;
        $this->assertFalse( $encryption->isEncryptedFile( $shortFile ), 'Should not detect too short file as encrypted' ) ;
        $this->assertFalse( $encryption->isEncryptedFile( $nonExistentFile ), 'Should return false for non-existent file' ) ;
        $this->assertFalse( $encryption->isEncryptedFile( $corruptedFile ), 'Should not detect corrupted file as valid encrypted file' ) ;
        $this->assertFalse( $encryption->isEncryptedFile( $ivSizeFile ), 'Should not detect all-zero IV as valid encrypted file' ) ;

        foreach ( [ $plainFile, $encryptedFile, $emptyFile, $shortFile, $corruptedFile, $ivSizeFile ] as $file )
        {
            if ( file_exists( $file ) )
            {
                unlink($file);
            }
        }
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testDecryptWithNullOutputFile(): void
    {
        $encryption = new OpenSSLFileEncryption($this->passphrase, $this->cipher);

        $encryptedFile = $encryption->encrypt( $this->inputFile );

        // Output file set to null: expect to create outputFile by removing FileExtension::ENCRYPTED from input
        $result = $encryption->decrypt( $encryptedFile );

        $this->assertEquals( $this->inputFile , $result ) ;
        $this->assertFileExists( $result );

        // Cleanup
        @unlink( $result );
    }

    // ---------------------------------------------------------------------
    // V2 format-specific tests
    // ---------------------------------------------------------------------

    public function testEncryptProducesV2MagicHeader(): void
    {
        $crypto = new OpenSSLFileEncryption( $this->passphrase ) ;
        $crypto->encrypt( $this->inputFile , $this->encryptedFile ) ;

        $data = file_get_contents( $this->encryptedFile ) ;
        $this->assertSame( 'OPHE' , substr( $data , 0 , 4 ) , 'V2 file must start with OPHE magic' ) ;
        $this->assertSame( 2 , ord( $data[ 4 ] ) , 'Version byte must be 2' ) ;

        // KDF byte must be one of the known algorithms
        $kdf = ord( $data[ 5 ] ) ;
        $this->assertContains( $kdf , [ 1 , 2 ] , 'KDF byte must be Argon2id (1) or PBKDF2 (2)' ) ;
    }

    public function testEncryptIsNonDeterministic(): void
    {
        // Same input + same passphrase must produce different ciphertexts
        // (fresh random salt + IV per encryption)
        $crypto = new OpenSSLFileEncryption( $this->passphrase ) ;

        $out1 = $this->testDir . '/one.enc' ;
        $out2 = $this->testDir . '/two.enc' ;

        $crypto->encrypt( $this->inputFile , $out1 ) ;
        $crypto->encrypt( $this->inputFile , $out2 ) ;

        $this->assertNotEquals
        (
            file_get_contents( $out1 ) ,
            file_get_contents( $out2 ) ,
            'Two encryptions of the same content must produce different ciphertexts'
        ) ;

        @unlink( $out1 ) ;
        @unlink( $out2 ) ;
    }

    public function testTamperingInCiphertextIsDetected(): void
    {
        $crypto = new OpenSSLFileEncryption( $this->passphrase ) ;
        $crypto->encrypt( $this->inputFile , $this->encryptedFile ) ;

        // Flip one byte in the ciphertext region (after the 34-byte V2 header,
        // before the last 16 bytes which hold the tag).
        $data = file_get_contents( $this->encryptedFile ) ;
        $tamperOffset = 34 ; // first byte of ciphertext
        $data[ $tamperOffset ] = chr( ord( $data[ $tamperOffset ] ) ^ 0xFF ) ;
        file_put_contents( $this->encryptedFile , $data ) ;

        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessage( 'Decryption failed due to incorrect passphrase or corrupted data.' ) ;

        $crypto->decrypt( $this->encryptedFile , $this->decryptedFile ) ;
    }

    public function testTamperingInAuthTagIsDetected(): void
    {
        $crypto = new OpenSSLFileEncryption( $this->passphrase ) ;
        $crypto->encrypt( $this->inputFile , $this->encryptedFile ) ;

        // Flip the last byte (part of the auth tag).
        $data = file_get_contents( $this->encryptedFile ) ;
        $lastByte = strlen( $data ) - 1 ;
        $data[ $lastByte ] = chr( ord( $data[ $lastByte ] ) ^ 0xFF ) ;
        file_put_contents( $this->encryptedFile , $data ) ;

        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessage( 'Decryption failed' ) ;

        $crypto->decrypt( $this->encryptedFile , $this->decryptedFile ) ;
    }

    public function testTruncatedV2PayloadIsRejected(): void
    {
        $crypto = new OpenSSLFileEncryption( $this->passphrase ) ;
        $crypto->encrypt( $this->inputFile , $this->encryptedFile ) ;

        // Truncate to half its size
        $data = file_get_contents( $this->encryptedFile ) ;
        file_put_contents( $this->encryptedFile , substr( $data , 0 , 30 ) ) ;

        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessage( 'V2 payload is shorter' ) ;

        $crypto->decrypt( $this->encryptedFile , $this->decryptedFile ) ;
    }

    public function testDecryptLegacyV1FileStillWorks(): void
    {
        // Manually craft a legacy V1 file (no magic, IV + AES-256-CBC ciphertext, raw passphrase)
        $passphrase = $this->passphrase ;
        $cipher     = 'aes-256-cbc' ;
        $plain      = 'Legacy content for backward-compat test' ;
        $iv         = random_bytes( openssl_cipher_iv_length( $cipher ) ) ;
        $ct         = openssl_encrypt( $plain , $cipher , $passphrase , OPENSSL_RAW_DATA , $iv ) ;
        $this->assertNotFalse( $ct ) ;

        $legacyFile = $this->testDir . '/legacy.enc' ;
        file_put_contents( $legacyFile , $iv . $ct ) ;

        $crypto = new OpenSSLFileEncryption( $passphrase , $cipher ) ;
        $result = $crypto->decrypt( $legacyFile , $this->decryptedFile ) ;

        $this->assertSame( $plain , file_get_contents( $result ) , 'Legacy V1 file must decrypt to original plaintext' ) ;

        @unlink( $legacyFile ) ;
    }

    public function testConstructorRejectsEmptyPassphrase(): void
    {
        $this->expectException( \InvalidArgumentException::class ) ;
        $this->expectExceptionMessage( 'Passphrase cannot be empty' ) ;
        new OpenSSLFileEncryption('') ;
    }

    public function testConstructorRejectsUnknownCipher(): void
    {
        $this->expectException( \InvalidArgumentException::class ) ;
        $this->expectExceptionMessage( "Cipher method 'not-a-cipher' is not available" ) ;
        new OpenSSLFileEncryption( $this->passphrase , 'not-a-cipher' ) ;
    }
}
