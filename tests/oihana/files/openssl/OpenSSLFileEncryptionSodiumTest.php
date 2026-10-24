<?php

namespace tests\oihana\files\openssl;

use oihana\files\openssl\enums\EncryptionFormat;
use oihana\files\openssl\OpenSSLFileEncryption;
use PHPUnit\Framework\TestCase;

/**
 * Reading across two hosts that do not have the same extensions.
 *
 * `ext-sodium` is a **suggestion** of this package, not a requirement, so the two ends
 * of an archive's life are not guaranteed to agree on it: a backup is written on a
 * server where it is loaded and restored wherever someone happens to be. The
 * {@see OpenSSLFileEncryptionTest} and {@see OpenSSLFileEncryptionV3Test} suites cover
 * the refusal through a seam; this one covers it in a process that genuinely lacks the
 * extension, because a seam proves the branch and not the environment.
 *
 * The child is started with `disable_functions=sodium_crypto_pwhash`, which removes
 * the function from the table so `function_exists()` answers false — the same answer a
 * build without the extension gives. A build that ignores the directive is detected
 * and the test skips rather than passing for the wrong reason.
 */
class OpenSSLFileEncryptionSodiumTest extends TestCase
{
    private const string PASSPHRASE = 'the-cross-host-passphrase' ;

    private string $testDir ;
    private string $script ;
    private string $plainFile ;
    private string $encryptedFile ;
    private string $decryptedFile ;

    protected function setUp(): void
    {
        if ( DIRECTORY_SEPARATOR === '\\' )
        {
            $this->markTestSkipped( 'The child process is launched with a POSIX command line.' ) ;
        }

        $this->testDir = sys_get_temp_dir() . '/OpenSSLFileEncryptionSodiumTest' ;

        if ( !is_dir( $this->testDir ) )
        {
            mkdir( $this->testDir , 0777 , true ) ;
        }

        $this->script        = $this->testDir . '/probe.php' ;
        $this->plainFile     = $this->testDir . '/archive.txt' ;
        $this->encryptedFile = $this->testDir . '/archive.txt.enc' ;
        $this->decryptedFile = $this->testDir . '/archive.out' ;

        file_put_contents( $this->script , <<<'CHILD'
        <?php

        use oihana\files\openssl\OpenSSLFileEncryption;

        require $argv[ 1 ] ;

        echo 'SODIUM=' , function_exists('sodium_crypto_pwhash') ? 'loaded' : 'absent' , "\n" ;

        $crypto = new OpenSSLFileEncryption( $argv[ 2 ] ) ;

        try
        {
            if ( $argv[ 3 ] === 'encrypt' )
            {
                $crypto->encrypt( $argv[ 4 ] , $argv[ 5 ] ) ;
            }
            else
            {
                $crypto->decrypt( $argv[ 4 ] , $argv[ 5 ] ) ;
            }

            echo "RESULT=ok\n" ;
            exit( 0 ) ;
        }
        catch ( Throwable $e )
        {
            echo 'RESULT=failed' , "\n" , 'MESSAGE=' , $e->getMessage() , "\n" ;
            exit( 1 ) ;
        }
        CHILD ) ;
    }

    protected function tearDown(): void
    {
        foreach ( glob( $this->testDir . '/*' ) ?: [] as $file )
        {
            is_dir( $file ) ? rmdir( $file ) : unlink( $file ) ;
        }

        if ( is_dir( $this->testDir ) )
        {
            rmdir( $this->testDir ) ;
        }
    }

    /**
     * @return array{0:int,1:string} Exit status and output.
     */
    private function runChild( bool $withSodium , string $mode , string $input , string $output ) :array
    {
        $command = sprintf
        (
            '%s%s %s %s %s %s %s %s 2>&1' ,
            escapeshellarg( PHP_BINARY ) ,
            $withSodium ? '' : ' -d ' . escapeshellarg( 'disable_functions=sodium_crypto_pwhash,sodium_memzero' ) ,
            escapeshellarg( $this->script ) ,
            escapeshellarg( dirname( __DIR__ , 4 ) . '/vendor/autoload.php' ) ,
            escapeshellarg( self::PASSPHRASE ) ,
            escapeshellarg( $mode ) ,
            escapeshellarg( $input ) ,
            escapeshellarg( $output )
        ) ;

        $lines  = [] ;
        $status = 0 ;
        exec( $command , $lines , $status ) ;

        return [ $status , implode( "\n" , $lines ) ] ;
    }

    private function assertChildLacksSodium( string $report ) :void
    {
        if ( !str_contains( $report , 'SODIUM=absent' ) )
        {
            $this->markTestSkipped( 'This PHP build does not honour disable_functions, so the child still has sodium.' ) ;
        }
    }

    /**
     * The measurement that put this guard here. Before it, the report on this exact
     * sequence was *"Decryption failed due to incorrect passphrase or corrupted data"*
     * — on an intact archive, with the right passphrase, with the real cause buried in
     * a wrapped exception no command line prints.
     */
    public function testAnArchiveWrittenWithSodiumIsRefusedByNameWhereItIsAbsent(): void
    {
        if ( !function_exists('sodium_crypto_pwhash') )
        {
            $this->markTestSkipped( 'Writing an Argon2id archive needs the extension this test then removes.' ) ;
        }

        file_put_contents( $this->plainFile , 'The nightly archive of a site.' ) ;

        ( new OpenSSLFileEncryption( self::PASSPHRASE ) )->encrypt( $this->plainFile , $this->encryptedFile ) ;

        $this->assertSame
        (
            EncryptionFormat::KDF_ARGON2ID ,
            ord( file_get_contents( $this->encryptedFile )[ 5 ] ) ,
            'The archive must really name Argon2id for this test to mean anything'
        ) ;

        [ $status , $report ] = $this->runChild( false , 'decrypt' , $this->encryptedFile , $this->decryptedFile ) ;

        $this->assertChildLacksSodium( $report ) ;

        $this->assertSame( 1 , $status , "The read must fail. The child said:\n" . $report ) ;

        $this->assertStringContainsString( 'requires the sodium extension' , $report , 'The refusal must name what is missing' ) ;
        $this->assertStringContainsString( 'passphrase is not in question' , $report , 'and clear the passphrase' ) ;
        $this->assertStringNotContainsString( 'corrupted data' , $report , 'and never call an intact archive corrupt' ) ;

        $this->assertFileDoesNotExist( $this->decryptedFile ) ;
    }

    /**
     * And the direction that must keep working: without sodium the library derives with
     * PBKDF2-SHA256, which is native to PHP, so the archive it writes reads anywhere —
     * including on the host that has the extension.
     */
    public function testAnArchiveWrittenWithoutSodiumReadsOnAHostThatHasIt(): void
    {
        $plain = 'The nightly archive of a site, written without sodium.' ;
        file_put_contents( $this->plainFile , $plain ) ;

        [ $status , $report ] = $this->runChild( false , 'encrypt' , $this->plainFile , $this->encryptedFile ) ;

        $this->assertChildLacksSodium( $report ) ;

        $this->assertSame( 0 , $status , "Encryption without sodium must succeed. The child said:\n" . $report ) ;

        $this->assertSame
        (
            EncryptionFormat::KDF_PBKDF2_SHA256 ,
            ord( file_get_contents( $this->encryptedFile )[ 5 ] ) ,
            'Without sodium the fallback KDF must be recorded in the header'
        ) ;

        $decrypted = ( new OpenSSLFileEncryption( self::PASSPHRASE ) )->decrypt( $this->encryptedFile , $this->decryptedFile ) ;

        $this->assertSame( $plain , file_get_contents( $decrypted ) ) ;
    }
}
