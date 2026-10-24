<?php

namespace tests\oihana\files\openssl;

use oihana\files\exceptions\DirectoryException;
use oihana\files\exceptions\FileException;
use oihana\files\openssl\enums\EncryptionFormat;
use oihana\files\openssl\OpenSSLFileEncryption;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function oihana\files\openssl\bestAvailableKdf;
use function oihana\files\openssl\deriveKey;
use function oihana\files\openssl\kdfIsAvailable;

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

        // ---- Recursively delete any files and subdirectories created during testing.
        if (file_exists($this->testDir)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->testDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($iterator as $entry) {
                $entry->isDir() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
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

    public function testEncryptProducesV3MagicHeader(): void
    {
        $crypto = new OpenSSLFileEncryption( $this->passphrase ) ;
        $crypto->encrypt( $this->inputFile , $this->encryptedFile ) ;

        $data = file_get_contents( $this->encryptedFile ) ;
        $this->assertSame( 'OPHE' , substr( $data , 0 , 4 ) , 'An encrypted file must start with the OPHE magic' ) ;
        $this->assertSame( 3 , ord( $data[ 4 ] ) , 'Version byte must be 3' ) ;

        // KDF byte must be one of the known algorithms
        $kdf = ord( $data[ 5 ] ) ;
        $this->assertContains( $kdf , [ 1 , 2 ] , 'KDF byte must be Argon2id (1) or PBKDF2 (2)' ) ;

        // And the byte V3 added: the block size the reader has to cut frames on.
        $this->assertSame
        (
            EncryptionFormat::DEFAULT_BLOCK_SIZE_EXPONENT ,
            ord( $data[ 6 ] ) ,
            'Block-size exponent byte must be the default'
        ) ;

        // 'Hello, World!' is 13 bytes: one frame, so header + 13 + one tag.
        $this->assertSame
        (
            EncryptionFormat::V3_HEADER_LENGTH + 13 + EncryptionFormat::GCM_TAG_LENGTH ,
            strlen( $data ) ,
            'A single-block file must be exactly header + ciphertext + one tag'
        ) ;
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

        // Flip one byte of the first frame's ciphertext: past the 31-byte V3 header,
        // before the 16 bytes of tag that close the frame.
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

    public function testTruncatedV3FileIsRejected(): void
    {
        $crypto = new OpenSSLFileEncryption( $this->passphrase ) ;
        $crypto->encrypt( $this->inputFile , $this->encryptedFile ) ;

        // Cut inside the header, so not even one frame is left.
        $data = file_get_contents( $this->encryptedFile ) ;
        file_put_contents( $this->encryptedFile , substr( $data , 0 , 30 ) ) ;

        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessage( 'V3 file is shorter than a header and one frame' ) ;

        $crypto->decrypt( $this->encryptedFile , $this->decryptedFile ) ;
    }

    /**
     * Builds a genuine V2 file — the format this library no longer writes — so the read
     * path that still has to open the archives of 2025 keeps being exercised by
     * something other than its own writer.
     */
    private function buildV2File( string $path , string $plaintext , ?int $kdf = null ) :void
    {
        $salt = random_bytes( EncryptionFormat::SALT_LENGTH ) ;
        $iv   = random_bytes( EncryptionFormat::GCM_IV_LENGTH ) ;
        $kdf  = $kdf ?? bestAvailableKdf() ;
        $key  = deriveKey( $this->passphrase , $salt , $kdf ) ;

        $tag        = '' ;
        $ciphertext = openssl_encrypt
        (
            $plaintext ,
            EncryptionFormat::DEFAULT_CIPHER ,
            $key ,
            OPENSSL_RAW_DATA ,
            $iv ,
            $tag ,
            '' ,
            EncryptionFormat::GCM_TAG_LENGTH
        ) ;

        file_put_contents
        (
            $path ,
            EncryptionFormat::MAGIC
            . chr( EncryptionFormat::VERSION_V2 )
            . chr( $kdf )
            . $salt
            . $iv
            . $ciphertext
            . $tag
        ) ;
    }

    public function testHandBuiltV2FileStillDecrypts(): void
    {
        $plain = 'An archive written before V3 existed.' ;
        $v2    = $this->testDir . '/handmade.v2.enc' ;

        $this->buildV2File( $v2 , $plain ) ;

        $crypto = new OpenSSLFileEncryption( $this->passphrase ) ;

        $this->assertSame( $plain , file_get_contents( $crypto->decrypt( $v2 , $this->decryptedFile ) ) ) ;
    }

    public function testTruncatedV2PayloadIsRejected(): void
    {
        $v2 = $this->testDir . '/handmade.v2.enc' ;

        $this->buildV2File( $v2 , 'An archive written before V3 existed.' ) ;
        file_put_contents( $v2 , substr( file_get_contents( $v2 ) , 0 , 30 ) ) ;

        $crypto = new OpenSSLFileEncryption( $this->passphrase ) ;

        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessage( 'V2 payload is shorter' ) ;

        $crypto->decrypt( $v2 , $this->decryptedFile ) ;
    }

    public function testTamperedHandBuiltV2FileIsDetected(): void
    {
        $v2 = $this->testDir . '/handmade.v2.enc' ;

        $this->buildV2File( $v2 , 'An archive written before V3 existed.' ) ;

        $data = file_get_contents( $v2 ) ;
        $at   = EncryptionFormat::HEADER_LENGTH + EncryptionFormat::SALT_LENGTH + EncryptionFormat::GCM_IV_LENGTH ;
        $data[ $at ] = chr( ord( $data[ $at ] ) ^ 0xFF ) ;
        file_put_contents( $v2 , $data ) ;

        $crypto = new OpenSSLFileEncryption( $this->passphrase ) ;

        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessage( 'Decryption failed due to incorrect passphrase or corrupted data.' ) ;

        $crypto->decrypt( $v2 , $this->decryptedFile ) ;
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

    public function testMaxInputBytesNullKeepsLegacyBehaviour(): void
    {
        $encryption = new OpenSSLFileEncryption( $this->passphrase , $this->cipher , null ) ;
        $encryption->encrypt( $this->inputFile , $this->encryptedFile ) ;
        $encryption->decrypt( $this->encryptedFile , $this->decryptedFile ) ;

        $this->assertSame( file_get_contents( $this->inputFile ) , file_get_contents( $this->decryptedFile ) ) ;
    }

    public function testMaxInputBytesUnderLimitAllowsEncrypt(): void
    {
        // 'Hello, World!' is 13 bytes — well under 1 KiB.
        $encryption = new OpenSSLFileEncryption( $this->passphrase , $this->cipher , 1024 ) ;
        $encryption->encrypt( $this->inputFile , $this->encryptedFile ) ;

        $this->assertFileExists( $this->encryptedFile ) ;
    }

    public function testMaxInputBytesExceededOnEncryptThrowsAndWritesNothing(): void
    {
        $encryption = new OpenSSLFileEncryption( $this->passphrase , $this->cipher , 5 ) ;

        $thrown = false ;
        try
        {
            $encryption->encrypt( $this->inputFile , $this->encryptedFile ) ;
        }
        catch ( RuntimeException $e )
        {
            $thrown = true ;
            $this->assertMatchesRegularExpression( '/exceeds maxInputBytes/i' , $e->getMessage() ) ;
        }

        $this->assertTrue( $thrown , 'encrypt() must throw when input exceeds maxInputBytes.' ) ;
        $this->assertFileDoesNotExist( $this->encryptedFile , 'No encrypted output must be written when the cap is exceeded.' ) ;
    }

    public function testMaxInputBytesExceededOnDecryptThrows(): void
    {
        // First encrypt without limit, then attempt to decrypt with a tiny limit.
        $writer = new OpenSSLFileEncryption( $this->passphrase , $this->cipher ) ;
        $writer->encrypt( $this->inputFile , $this->encryptedFile ) ;

        $reader = new OpenSSLFileEncryption( $this->passphrase , $this->cipher , 5 ) ;

        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessageMatches( '/exceeds maxInputBytes/i' ) ;

        $reader->decrypt( $this->encryptedFile , $this->decryptedFile ) ;
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testEncryptFailsWhenOutputPathIsADirectory(): void
    {
        $encryption = new OpenSSLFileEncryption( $this->passphrase , $this->cipher ) ;

        // Output path is an existing (writable) directory -> file_put_contents() returns false.
        $dir = $this->testDir . '/enc_out_dir';
        if ( !is_dir( $dir ) ) { mkdir( $dir ) ; }

        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessageMatches( '/Encryption failed, file write failed/' ) ;

        $encryption->encrypt( $this->inputFile , $dir ) ;
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testDecryptFailsWhenOutputDirectoryNotWritable(): void
    {
        if ( DIRECTORY_SEPARATOR === '\\' )
        {
            $this->markTestSkipped( 'Permission tests are not reliable on Windows.' ) ;
        }

        $encryption = new OpenSSLFileEncryption( $this->passphrase , $this->cipher ) ;
        $encryption->encrypt( $this->inputFile , $this->encryptedFile ) ;

        $roDir = $this->testDir . '/ro_out_dir';
        if ( !is_dir( $roDir ) ) { mkdir( $roDir ) ; }
        chmod( $roDir , 0555 ) ;

        try
        {
            $this->expectException( RuntimeException::class ) ;
            $this->expectExceptionMessage( 'Decryption failed, output directory is not writable.' ) ;
            $encryption->decrypt( $this->encryptedFile , $roDir . '/out.txt' ) ;
        }
        finally
        {
            @chmod( $roDir , 0777 ) ;
            @rmdir( $roDir ) ;
        }
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testDecryptFailsWhenOutputFileNotWritable(): void
    {
        if ( DIRECTORY_SEPARATOR === '\\' )
        {
            $this->markTestSkipped( 'Permission tests are not reliable on Windows.' ) ;
        }

        $encryption = new OpenSSLFileEncryption( $this->passphrase , $this->cipher ) ;
        $encryption->encrypt( $this->inputFile , $this->encryptedFile ) ;

        $roFile = $this->testDir . '/ro_target.txt';
        file_put_contents( $roFile , 'old' ) ;
        chmod( $roFile , 0444 ) ;

        try
        {
            $this->expectException( RuntimeException::class ) ;
            $this->expectExceptionMessage( 'Decryption failed, output file is not writable.' ) ;
            $encryption->decrypt( $this->encryptedFile , $roFile ) ;
        }
        finally
        {
            @chmod( $roFile , 0644 ) ;
            @unlink( $roFile ) ;
        }
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testDecryptFailsWhenOutputPathIsADirectory(): void
    {
        $encryption = new OpenSSLFileEncryption( $this->passphrase , $this->cipher ) ;
        $encryption->encrypt( $this->inputFile , $this->encryptedFile ) ;

        // Decryption succeeds, but the write target is a directory -> file_put_contents() fails.
        $dir = $this->testDir . '/dec_out_dir';
        if ( !is_dir( $dir ) ) { mkdir( $dir ) ; }

        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessageMatches( '/Decryption failed, file write failed/' ) ;

        $encryption->decrypt( $this->encryptedFile , $dir ) ;
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testDecryptWrapsKdfErrorOnCorruptAlgorithmByte(): void
    {
        $encryption = new OpenSSLFileEncryption( $this->passphrase , $this->cipher ) ;
        $encryption->encrypt( $this->inputFile , $this->encryptedFile ) ;

        // The KDF identifier sits right after MAGIC + VERSION; set it to an unknown value.
        $data   = file_get_contents( $this->encryptedFile ) ;
        $offset = EncryptionFormat::MAGIC_LENGTH + EncryptionFormat::VERSION_LENGTH ;
        $data[ $offset ] = chr( 99 ) ;

        $corrupt = $this->testDir . '/corrupt_kdf.enc';
        file_put_contents( $corrupt , $data ) ;

        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessage( 'Decryption failed due to incorrect passphrase or corrupted data.' ) ;

        $encryption->decrypt( $corrupt , $this->decryptedFile ) ;
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testDecryptLegacyThrowsWhenPayloadTooShort(): void
    {
        $encryption = new OpenSSLFileEncryption( $this->passphrase , $this->cipher ) ;

        // Not a V2 payload and shorter than the IV length -> legacy "too short" guard.
        $shortFile = $this->testDir . '/short.enc';
        file_put_contents( $shortFile , 'short' ) ;

        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessage( 'file is too short to contain IV' ) ;

        $encryption->decrypt( $shortFile , $this->decryptedFile ) ;
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testDecryptLegacyThrowsOnWrongPassphrase(): void
    {
        // Craft a legacy V1 payload (IV + raw CBC ciphertext) using a known passphrase...
        $ivLength = openssl_cipher_iv_length( $this->cipher ) ;
        $iv       = random_bytes( $ivLength ) ;
        $cipher   = openssl_encrypt( 'legacy data' , $this->cipher , 'right-pass' , OPENSSL_RAW_DATA , $iv ) ;

        $legacyFile = $this->testDir . '/legacy.enc';
        file_put_contents( $legacyFile , $iv . $cipher ) ;

        // ...then decrypt with a different passphrase -> openssl_decrypt() returns false.
        $reader = new OpenSSLFileEncryption( 'wrong-pass' , $this->cipher ) ;

        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessage( 'Decryption failed due to incorrect passphrase or corrupted data.' ) ;

        $reader->decrypt( $legacyFile , $this->decryptedFile ) ;
    }

    public function testIsEncryptedFileDetectsLegacyHeuristic(): void
    {
        $encryption = new OpenSSLFileEncryption( $this->passphrase , $this->cipher ) ;

        // Non-V2 binary blob with a non-zero, non-printable IV passes the legacy heuristic.
        $blob = $this->testDir . '/legacy_blob.bin';
        file_put_contents( $blob , str_repeat( chr( 1 ) , 64 ) ) ;

        $this->assertTrue( $encryption->isEncryptedFile( $blob ) ) ;
    }

    // ---------------------------------------------------------------------
    // The memory guard on the legacy read paths
    // ---------------------------------------------------------------------

    /**
     * An instance that states its memory budget instead of inheriting the runner's.
     *
     * The budget is the one thing these tests cannot take from the environment: on a
     * runner with no `memory_limit` the real budget is unknown, and on one with a
     * limit it is hundreds of megabytes — either way the guard would never fire on a
     * fixture small enough to belong in a test suite.
     */
    private function cryptoWithBudget( ?int $budget ) :OpenSSLFileEncryption
    {
        return new class( $this->passphrase , $this->cipher , $budget ) extends OpenSSLFileEncryption
        {
            public function __construct( string $passphrase , string $cipher , private readonly ?int $budget )
            {
                parent::__construct( $passphrase , $cipher ) ;
            }

            protected function memoryBudget() : ?int
            {
                return $this->budget ;
            }
        } ;
    }

    private function buildV1File( string $path , string $plaintext ) :void
    {
        $iv = random_bytes( openssl_cipher_iv_length( $this->cipher ) ) ;
        $ct = openssl_encrypt( $plaintext , $this->cipher , $this->passphrase , OPENSSL_RAW_DATA , $iv ) ;

        file_put_contents( $path , $iv . $ct ) ;
    }

    /**
     * The failure this guard exists for did not look like a failure. Reading a V2 file
     * past the budget either threw a fatal error naming the limit or, with
     * `memory_limit = -1`, had the process killed by the kernel — no exception, no
     * trace, nothing in any log but a line in `dmesg`. A backup that neither succeeded
     * nor failed.
     */
    public function testAV2FileBeyondTheMemoryBudgetIsRefusedWithAnExplanation(): void
    {
        $v2 = $this->testDir . '/big.v2.enc' ;
        $this->buildV2File( $v2 , str_repeat( 'x' , 4096 ) ) ;

        $crypto = $this->cryptoWithBudget( 1024 ) ;

        try
        {
            $crypto->decrypt( $v2 , $this->decryptedFile ) ;
            $this->fail( 'A legacy file past the memory budget must be refused.' ) ;
        }
        catch ( RuntimeException $e )
        {
            $message = $e->getMessage() ;

            $this->assertStringContainsString( 'is a V2 file of' , $message , 'The refusal must name the format' ) ;
            $this->assertStringContainsString( 'memory budget here is 1024 bytes' , $message , 'and the budget it was measured against' ) ;
            $this->assertStringContainsString( 'Raise memory_limit' , $message , 'and what the operator can do about it' ) ;
        }
    }

    public function testAV1FileBeyondTheMemoryBudgetIsRefused(): void
    {
        $v1 = $this->testDir . '/big.v1.enc' ;
        $this->buildV1File( $v1 , str_repeat( 'x' , 4096 ) ) ;

        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessage( 'is a V1 file of' ) ;

        $this->cryptoWithBudget( 1024 )->decrypt( $v1 , $this->decryptedFile ) ;
    }

    public function testALegacyFileWithinTheMemoryBudgetIsRead(): void
    {
        $plain = str_repeat( 'x' , 4096 ) ;
        $v2    = $this->testDir . '/small.v2.enc' ;

        $this->buildV2File( $v2 , $plain ) ;

        $crypto = $this->cryptoWithBudget( 64 * 1024 * 1024 ) ;

        $this->assertSame( $plain , file_get_contents( $crypto->decrypt( $v2 , $this->decryptedFile ) ) ) ;
    }

    /**
     * `null` is *unknown*, not *unlimited*. Refusing on a figure nobody has would
     * break every read that works today on a host without `/proc/meminfo`.
     */
    public function testAnUnknownMemoryBudgetLetsTheReadThrough(): void
    {
        $plain = str_repeat( 'x' , 4096 ) ;
        $v2    = $this->testDir . '/unknown.v2.enc' ;

        $this->buildV2File( $v2 , $plain ) ;

        $crypto = $this->cryptoWithBudget( null ) ;

        $this->assertSame( $plain , file_get_contents( $crypto->decrypt( $v2 , $this->decryptedFile ) ) ) ;
    }

    /**
     * And the point of the whole lot: the guard is on the legacy paths only. A V3 file
     * reads under a budget of one byte, because it never holds more than a block.
     */
    public function testAV3FileIsNotSubjectToTheMemoryGuard(): void
    {
        $plain = str_repeat( 'x' , 200_000 ) ;
        file_put_contents( $this->inputFile , $plain ) ;

        $crypto = $this->cryptoWithBudget( 1 ) ;
        $crypto->encrypt( $this->inputFile , $this->encryptedFile ) ;

        $this->assertSame( $plain , file_get_contents( $crypto->decrypt( $this->encryptedFile , $this->decryptedFile ) ) ) ;
    }

    // ---------------------------------------------------------------------
    // The V2 read path, on files this library no longer writes
    // ---------------------------------------------------------------------

    public function testDecryptV2WrapsKdfErrorOnCorruptAlgorithmByte(): void
    {
        $v2 = $this->testDir . '/handmade.v2.enc' ;

        $this->buildV2File( $v2 , 'An archive written before V3 existed.' ) ;

        // The KDF identifier sits right after MAGIC + VERSION.
        $data   = file_get_contents( $v2 ) ;
        $offset = EncryptionFormat::MAGIC_LENGTH + EncryptionFormat::VERSION_LENGTH ;
        $data[ $offset ] = chr( 99 ) ;
        file_put_contents( $v2 , $data ) ;

        $crypto = new OpenSSLFileEncryption( $this->passphrase , $this->cipher ) ;

        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessage( 'Decryption failed due to incorrect passphrase or corrupted data.' ) ;

        $crypto->decrypt( $v2 , $this->decryptedFile ) ;
    }

    /**
     * The legacy path still writes its plaintext in one call, so its write failure has
     * its own line to reach.
     */
    public function testDecryptV2FailsWhenTheOutputPathIsADirectory(): void
    {
        $v2 = $this->testDir . '/handmade.v2.enc' ;

        $this->buildV2File( $v2 , 'An archive written before V3 existed.' ) ;

        $dir = $this->testDir . '/legacy_out_dir' ;

        if ( !is_dir( $dir ) )
        {
            mkdir( $dir ) ;
        }

        $crypto = new OpenSSLFileEncryption( $this->passphrase , $this->cipher ) ;

        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessageMatches( '/Decryption failed, file write failed/' ) ;

        $crypto->decrypt( $v2 , $dir ) ;
    }

    public function testIsEncryptedFileRecognisesAV2File(): void
    {
        $v2 = $this->testDir . '/handmade.v2.enc' ;

        $this->buildV2File( $v2 , 'An archive written before V3 existed.' ) ;

        $crypto = new OpenSSLFileEncryption( $this->passphrase , $this->cipher ) ;

        $this->assertTrue( $crypto->isEncryptedFile( $v2 ) ) ;
    }

    public function testIsEncryptedFileRefusesAV2FileTooShortToHoldOne(): void
    {
        $v2 = $this->testDir . '/handmade.v2.enc' ;

        $this->buildV2File( $v2 , 'An archive written before V3 existed.' ) ;
        file_put_contents( $v2 , substr( file_get_contents( $v2 ) , 0 , 20 ) ) ;

        $crypto = new OpenSSLFileEncryption( $this->passphrase , $this->cipher ) ;

        $this->assertFalse( $crypto->isEncryptedFile( $v2 ) ) ;
    }

    // ---------------------------------------------------------------------
    // A KDF the reader's own host cannot run
    // ---------------------------------------------------------------------

    /**
     * An instance that reads as though `ext-sodium` were absent — which, since the
     * extension is a suggestion of this package and not a requirement, is the state of
     * any number of hosts a backup might be restored on.
     */
    private function cryptoWithoutSodium() :OpenSSLFileEncryption
    {
        return new class( $this->passphrase , $this->cipher ) extends OpenSSLFileEncryption
        {
            protected function isKdfAvailable( int $kdfAlgorithm ) : bool
            {
                return kdfIsAvailable( $kdfAlgorithm , false ) ;
            }
        } ;
    }

    public function testAnArgon2idV2FileWithoutSodiumNamesTheMissingExtension(): void
    {
        if ( !function_exists('sodium_crypto_pwhash') )
        {
            $this->markTestSkipped( 'Writing an Argon2id file needs the extension this test then pretends is absent.' ) ;
        }

        $v2 = $this->testDir . '/argon2id.v2.enc' ;
        $this->buildV2File( $v2 , 'An archive written where sodium was loaded.' , EncryptionFormat::KDF_ARGON2ID ) ;

        try
        {
            $this->cryptoWithoutSodium()->decrypt( $v2 , $this->decryptedFile ) ;
            $this->fail( 'A KDF this host cannot run must be refused.' ) ;
        }
        catch ( RuntimeException $e )
        {
            $message = $e->getMessage() ;

            $this->assertStringContainsString( 'sodium extension' , $message , 'The refusal must name what is missing' ) ;
            $this->assertStringContainsString( 'passphrase is not in question' , $message , 'and clear the passphrase, which is where the old message sent people' ) ;
            $this->assertStringNotContainsString( 'corrupted data' , $message , 'and must not call an intact archive corrupt' ) ;
        }
    }

    /**
     * The other half of the same property: PBKDF2 is `hash_pbkdf2()`, native to PHP, so
     * a file written without sodium reads on a host without sodium.
     */
    public function testAPbkdf2V2FileReadsWithoutSodium(): void
    {
        $plain = 'An archive written where sodium was not loaded.' ;
        $v2    = $this->testDir . '/pbkdf2.v2.enc' ;

        $this->buildV2File( $v2 , $plain , EncryptionFormat::KDF_PBKDF2_SHA256 ) ;

        $this->assertSame
        (
            $plain ,
            file_get_contents( $this->cryptoWithoutSodium()->decrypt( $v2 , $this->decryptedFile ) )
        ) ;
    }

    /**
     * An unknown KDF byte must keep the generic message: it is a corrupt or forged
     * header, not a missing extension, and a forger gets no confirmation of which byte
     * they got wrong.
     */
    public function testAnUnknownKdfByteKeepsTheGenericMessageEvenWithoutSodium(): void
    {
        $v2 = $this->testDir . '/unknown-kdf.v2.enc' ;
        $this->buildV2File( $v2 , 'An archive written before V3 existed.' , EncryptionFormat::KDF_PBKDF2_SHA256 ) ;

        $data   = file_get_contents( $v2 ) ;
        $offset = EncryptionFormat::MAGIC_LENGTH + EncryptionFormat::VERSION_LENGTH ;
        $data[ $offset ] = chr( 99 ) ;
        file_put_contents( $v2 , $data ) ;

        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessage( 'Decryption failed due to incorrect passphrase or corrupted data.' ) ;

        $this->cryptoWithoutSodium()->decrypt( $v2 , $this->decryptedFile ) ;
    }
}
