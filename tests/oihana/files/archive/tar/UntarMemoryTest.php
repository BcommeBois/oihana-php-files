<?php

namespace tests\oihana\files\archive\tar;

use PHPUnit\Framework\TestCase;

use function oihana\files\archive\tar\tarBinary;
use function oihana\files\deleteDirectory;

/**
 * What an extraction costs in memory, measured in a process that has a limit.
 *
 * It cannot be asserted in the process running the tests: lowering `memory_limit` here
 * would apply to PHPUnit, which has already allocated more than a meaningful limit, and
 * exhausting the limit is a fatal error rather than an exception — nothing to catch,
 * nothing to assert.
 *
 * What it pins: extracting through the system `tar` costs PHP nothing proportional to
 * the archive. Through PharData, decompressing means rebuilding the archive in memory,
 * measured on 2026-10-24 at ×2.26 of a 60 MB archive and ×2.04 of a 200 MB one — about
 * 5 GB for a 2.45 GB backup, which is why a restore of a large site could not run.
 *
 * Contributes no line coverage: coverage does not follow a subprocess. The lines it
 * exercises are covered by {@see UntarEngineTest} and {@see UntarWithBinaryTest} on
 * archives small enough to stay in memory. This one is here for the measurement.
 */
class UntarMemoryTest extends TestCase
{
    private const int PAYLOAD_BYTES = 100 * 1024 * 1024 ;

    private const string MEMORY_LIMIT = '48M' ;

    /**
     * How much more the large archive may cost than a tiny one.
     *
     * An absolute ceiling would measure the wrong thing: a child that requires this
     * package's autoloader peaks at some 4 MiB before it has opened anything, and that
     * figure belongs to Composer. What matters is the **slope** — a hundredfold increase
     * in archive size must not buy a hundredfold increase in memory.
     *
     * Measured on 2026-10-24, step by step: `untarWithBinary()` adds nothing at all, and
     * the difference between the two children — 13.6 MiB against 20.9 MiB — is allocated
     * by `assertTar()`, which asks `finfo` for the archive's MIME type. That cost does
     * **not** grow with the archive: measured on four sizes, `finfo` takes a flat
     * 7.0 MiB on a `.tar` of 1 MB or 400 MB alike, and a flat 14.0 MiB on a `.tar.gz`
     * past some ten megabytes. It steps once and then plateaus, so the 7 MiB seen here
     * is that step, not a slope — a 2.45 GB archive costs the same 14 MiB.
     *
     * 12 MiB covers the step while leaving no room for an engine that rebuilds the
     * archive in memory: PharData needs about 200 MiB for this one, and ×2 of whatever
     * it is given.
     */
    private const int PEAK_DIFFERENCE_CEILING = 12 * 1024 * 1024 ;

    private string $root ;
    private string|false $override ;

    protected function setUp() :void
    {
        // The suite may be running with OIHANA_TAR_BINARY declared empty, which forces
        // PharData everywhere. These tests are about the binary engine, so they look for
        // it themselves rather than skipping — a skip is a failure in this project.

        $this->override = getenv( 'OIHANA_TAR_BINARY' ) ;

        putenv( 'OIHANA_TAR_BINARY' ) ;

        if ( DIRECTORY_SEPARATOR === '\\' )
        {
            $this->markTestSkipped( 'The child process is launched with a POSIX command line.' ) ;
        }

        if ( tarBinary( refresh: true ) === null )
        {
            $this->markTestSkipped( 'No GNU tar on this host, so there is no streaming engine to measure.' ) ;
        }

        $this->root = sys_get_temp_dir() . '/oihana-untar-memory-' . bin2hex( random_bytes( 6 ) ) ;

        mkdir( $this->root . '/tree' , 0777 , true ) ;
    }

    protected function tearDown() :void
    {
        putenv( $this->override === false ? 'OIHANA_TAR_BINARY' : 'OIHANA_TAR_BINARY=' . $this->override ) ;
        tarBinary( refresh: true ) ;

        deleteDirectory( $this->root ) ;
    }

    /**
     * Writes `$parts` mebibytes into a directory of its own and archives it.
     *
     * A mebibyte at a time: `random_bytes( 100 MiB )` would make this process hold what
     * the child is being tested for not holding.
     */
    private function writeArchive( string $label , int $parts ) :string
    {
        $tree = $this->root . '/' . $label ;

        mkdir( $tree , 0777 , true ) ;

        $chunk = random_bytes( 1024 * 1024 ) ;

        for ( $i = 0 ; $i < $parts ; $i++ )
        {
            file_put_contents( sprintf( '%s/part%03d.bin' , $tree , $i ) , $chunk ) ;
        }

        $archive = $this->root . '/' . $label . '.tar.gz' ;

        $process = proc_open
        (
            [ tarBinary() , '-C' , $this->root , '-czf' , $archive , $label ] ,
            [ 1 => [ 'pipe' , 'w' ] , 2 => [ 'pipe' , 'w' ] ] ,
            $pipes
        ) ;

        fclose( $pipes[ 1 ] ) ;
        fclose( $pipes[ 2 ] ) ;

        $this->assertSame( 0 , proc_close( $process ) , 'The fixture archive must be created' ) ;

        return $archive ;
    }

    /**
     * Extracts one archive in a child with a stated limit and returns its peak.
     */
    private function peakOfExtracting( string $archive , string $label ) :int
    {
        $script = $this->root . '/extract.php' ;

        $this->writeChildScript( $script ) ;

        $command = sprintf
        (
            '%s -d memory_limit=%s %s %s %s %s 2>&1' ,
            escapeshellarg( PHP_BINARY ) ,
            escapeshellarg( self::MEMORY_LIMIT ) ,
            escapeshellarg( $script ) ,
            escapeshellarg( dirname( __DIR__ , 5 ) . '/vendor/autoload.php' ) ,
            escapeshellarg( $archive ) ,
            escapeshellarg( $this->root . '/out-' . $label )
        ) ;

        $output = [] ;
        $status = 0 ;
        exec( $command , $output , $status ) ;

        $report = implode( "\n" , $output ) ;

        $this->assertSame
        (
            0 ,
            $status ,
            sprintf( "Extracting %s under memory_limit=%s must succeed. The child said:\n%s" , basename( $archive ) , self::MEMORY_LIMIT , $report )
        ) ;

        $this->assertSame( 1 , preg_match( '/^PEAK=(\d+)$/m' , $report , $matches ) , 'The child must report its peak' ) ;

        return (int) $matches[ 1 ] ;
    }

    private function writeChildScript( string $path ) :void
    {
        file_put_contents( $path , <<<'CHILD'
        <?php

        use function oihana\files\archive\tar\untar;

        require $argv[ 1 ] ;

        untar( $argv[ 2 ] , $argv[ 3 ] ) ;

        echo 'PEAK=' , memory_get_peak_usage( true ) , "\n" ;
        CHILD ) ;
    }

    public function testExtractingCostsTheSameWhateverTheArchiveWeighs(): void
    {
        $small = $this->writeArchive( 'small' , 1 ) ;
        $large = $this->writeArchive( 'large' , (int) ( self::PAYLOAD_BYTES / ( 1024 * 1024 ) ) ) ;

        $this->assertGreaterThan
        (
            50 * 1024 * 1024 ,
            filesize( $large ) ,
            'The large archive must really be larger than the limit the children run under'
        ) ;

        $peakOfSmall = $this->peakOfExtracting( $small , 'small' ) ;
        $peakOfLarge = $this->peakOfExtracting( $large , 'large' ) ;

        $this->assertCount( 100 , glob( $this->root . '/out-large/large/*.bin' ) , 'Every member must be on disk' ) ;

        $this->assertLessThan
        (
            self::PEAK_DIFFERENCE_CEILING ,
            $peakOfLarge - $peakOfSmall ,
            sprintf
            (
                'A %d-byte archive peaked at %d bytes against %d for a 1 MiB one: the extraction is no longer streaming.' ,
                filesize( $large ) ,
                $peakOfLarge ,
                $peakOfSmall
            )
        ) ;
    }
}
