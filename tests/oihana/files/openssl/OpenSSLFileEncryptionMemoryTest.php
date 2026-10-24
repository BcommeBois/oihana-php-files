<?php

namespace tests\oihana\files\openssl;

use PHPUnit\Framework\TestCase;

/**
 * The one property of this lot that cannot be asserted in the process running the
 * tests: that encryption and decryption hold a block, not a file.
 *
 * It needs a process of its own for a plain reason. Lowering `memory_limit` here would
 * apply to PHPUnit too, which has already allocated more than the limit a meaningful
 * test would set; and exhausting the limit is a **fatal error**, not an exception, so
 * there would be nothing to catch and nothing to assert. With `memory_limit = -1` it
 * is worse: the kernel's OOM killer ends the process, and a killed test run reports
 * nothing at all.
 *
 * So the round trip runs in a child with a stated limit, and prints what it peaked at.
 *
 * This test contributes no line coverage — coverage does not follow a subprocess — so
 * every line it exercises is covered by {@see OpenSSLFileEncryptionV3Test} as well, on
 * files small enough to stay in memory. This one is here for the measurement.
 */
class OpenSSLFileEncryptionMemoryTest extends TestCase
{
    /**
     * Payload size. Larger than the child's `memory_limit`, which is the whole point:
     * under the format this replaced, reading it needed about four times this —
     * 800 MiB — and PHP either threw a fatal error or was killed outright.
     */
    private const int PAYLOAD_BYTES = 100 * 1024 * 1024 ;

    /**
     * The limit the child runs under. Below the payload, above a block.
     */
    private const string MEMORY_LIMIT = '48M' ;

    /**
     * The ceiling the measurement must stay under.
     *
     * Measured on 2026-10-24, PHP 8.4.11, default block size of 8 MiB: a 100 MiB round
     * trip peaked at 31 506 432 bytes — one block, its ciphertext, and the baseline of
     * a PHP process. 40 MiB leaves room for a different allocator without leaving room
     * for a regression: anything that reads a file whole lands far above it.
     */
    private const int PEAK_CEILING = 40 * 1024 * 1024 ;

    private string $testDir ;

    protected function setUp(): void
    {
        if ( DIRECTORY_SEPARATOR === '\\' )
        {
            $this->markTestSkipped( 'The child process is launched with a POSIX command line.' ) ;
        }

        $this->testDir = sys_get_temp_dir() . '/OpenSSLFileEncryptionMemoryTest' ;

        if ( !is_dir( $this->testDir ) )
        {
            mkdir( $this->testDir , 0777 , true ) ;
        }
    }

    protected function tearDown(): void
    {
        foreach ( glob( $this->testDir . '/*' ) ?: [] as $file )
        {
            if ( is_file( $file ) )
            {
                unlink( $file ) ;
            }
        }

        if ( is_dir( $this->testDir ) )
        {
            rmdir( $this->testDir ) ;
        }
    }

    /**
     * Writes the payload a mebibyte at a time — `random_bytes( 100 MiB )` would make
     * this process hold what the child is being tested for not holding.
     */
    private function writePayload( string $path ) :void
    {
        $chunk  = random_bytes( 1024 * 1024 ) ;
        $handle = fopen( $path , 'wb' ) ;

        for ( $written = 0 ; $written < self::PAYLOAD_BYTES ; $written += strlen( $chunk ) )
        {
            fwrite( $handle , $chunk ) ;
        }

        fclose( $handle ) ;
    }

    private function writeChildScript( string $path ) :void
    {
        file_put_contents( $path , <<<'CHILD'
        <?php

        use oihana\files\openssl\OpenSSLFileEncryption;

        require $argv[ 1 ] ;

        $crypto = new OpenSSLFileEncryption( 'the-memory-test-passphrase' ) ;

        $encrypted = $crypto->encrypt( $argv[ 2 ] , $argv[ 3 ] ) ;
        $decrypted = $crypto->decrypt( $encrypted , $argv[ 4 ] ) ;

        $identical = hash_file( 'sha256' , $argv[ 2 ] ) === hash_file( 'sha256' , $decrypted ) ;

        echo 'ROUNDTRIP=' , $identical ? 'identical' : 'mismatch' , "\n" ;
        echo 'PEAK=' , memory_get_peak_usage( true ) , "\n" ;

        exit( $identical ? 0 : 1 ) ;
        CHILD ) ;
    }

    public function testAFileLargerThanTheMemoryLimitRoundTripsAtConstantMemory(): void
    {
        $payload   = $this->testDir . '/payload.bin' ;
        $encrypted = $this->testDir . '/payload.bin.enc' ;
        $decrypted = $this->testDir . '/payload.out' ;
        $script    = $this->testDir . '/roundtrip.php' ;
        $autoload  = dirname( __DIR__ , 4 ) . '/vendor/autoload.php' ;

        $this->writePayload( $payload ) ;
        $this->writeChildScript( $script ) ;

        $command = sprintf
        (
            '%s -d memory_limit=%s %s %s %s %s %s 2>&1' ,
            escapeshellarg( PHP_BINARY ) ,
            escapeshellarg( self::MEMORY_LIMIT ) ,
            escapeshellarg( $script ) ,
            escapeshellarg( $autoload ) ,
            escapeshellarg( $payload ) ,
            escapeshellarg( $encrypted ) ,
            escapeshellarg( $decrypted )
        ) ;

        $output = [] ;
        $status = 0 ;
        exec( $command , $output , $status ) ;

        $report = implode( "\n" , $output ) ;

        $this->assertSame
        (
            0 ,
            $status ,
            sprintf
            (
                "A %d-byte round trip under memory_limit=%s must succeed. The child said:\n%s" ,
                self::PAYLOAD_BYTES ,
                self::MEMORY_LIMIT ,
                $report
            )
        ) ;

        $this->assertStringContainsString( 'ROUNDTRIP=identical' , $report , 'The plaintext must come back byte for byte' ) ;

        $this->assertSame( 1 , preg_match( '/^PEAK=(\d+)$/m' , $report , $matches ) , 'The child must report its peak' ) ;

        $peak = (int) $matches[ 1 ] ;

        $this->assertLessThan
        (
            self::PEAK_CEILING ,
            $peak ,
            sprintf
            (
                'Peak memory was %d bytes for a %d-byte file: the read is no longer proportional to a block.' ,
                $peak ,
                self::PAYLOAD_BYTES
            )
        ) ;
    }
}
