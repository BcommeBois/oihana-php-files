<?php

namespace oihana\files;

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
        $this->encryptedFile = $this->testDir . '/encrypted.txt';
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
        $encryption = new OpenSSLFileEncryption($this->passphrase, $this->cipher);
        $this->assertTrue(true ) ; // Placeholder assertion
    }

    public function testEncryptAndDecrypt()
    {
        $encryption = new OpenSSLFileEncryption($this->passphrase, $this->cipher);

        // Test encryption
        $result = $encryption->encrypt($this->inputFile, $this->encryptedFile);
        $this->assertTrue($result);
        $this->assertFileExists($this->encryptedFile);

        // Test decryption
        $result = $encryption->decrypt($this->encryptedFile, $this->decryptedFile);
        $this->assertTrue($result);
        $this->assertFileExists($this->decryptedFile);

        // Verify that the decrypted file matches the original
        $originalContent = file_get_contents($this->inputFile);
        $decryptedContent = file_get_contents($this->decryptedFile);
        $this->assertEquals($originalContent, $decryptedContent);
    }

    public function testEncryptWithNonExistentInputFile()
    {
        $encryption = new OpenSSLFileEncryption($this->passphrase, $this->cipher);
        $nonExistentFile = $this->testDir . '/nonexistent.txt';

        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessage("Encryption failed, the input file not exist.");

        $encryption->encrypt($nonExistentFile, $this->encryptedFile);
    }

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

    public function testEncryptWithNonExistentOutputDirectory()
    {
        $encryption = new OpenSSLFileEncryption($this->passphrase, $this->cipher);

        $nonExistentDir = $this->testDir . '/nonexistent_directory';
        $nonExistentFile = $nonExistentDir . '/encrypted.txt';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Encryption failed, output directory does not exist.");
        $encryption->encrypt($this->inputFile, $nonExistentFile);
    }

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

}
