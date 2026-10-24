<?php

namespace tests\oihana\files\openssl;

use PHPUnit\Framework\TestCase;

use function oihana\files\openssl\availableMemoryBytes;

class AvailableMemoryBytesTest extends TestCase
{
    private string $testDir ;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/AvailableMemoryBytesTest' ;

        if ( !is_dir( $this->testDir ) )
        {
            mkdir( $this->testDir , 0777 , true ) ;
        }
    }

    protected function tearDown(): void
    {
        foreach ( glob( $this->testDir . '/*' ) ?: [] as $file )
        {
            unlink( $file ) ;
        }

        if ( is_dir( $this->testDir ) )
        {
            rmdir( $this->testDir ) ;
        }
    }

    /**
     * Writes a `/proc/meminfo` lookalike and returns its path.
     */
    private function meminfo( string $contents ) :string
    {
        $path = $this->testDir . '/meminfo' ;
        file_put_contents( $path , $contents ) ;
        return $path ;
    }

    public function testPositiveMemoryLimitIsTheBudget(): void
    {
        $this->assertSame( 536870912 , availableMemoryBytes( '512M' , '/nonexistent' ) ) ;
    }

    /**
     * The order matters, not just the two sources: `memory_limit` is the ceiling PHP
     * enforces itself, so a host with 64 GiB free still cannot allocate past it.
     */
    public function testMemInfoIsNotConsultedWhenMemoryLimitApplies(): void
    {
        $meminfo = $this->meminfo( "MemAvailable:   67108864 kB\n" ) ;

        $this->assertSame( 268435456 , availableMemoryBytes( '256M' , $meminfo ) ) ;
    }

    public function testUnlimitedFallsBackToMemAvailable(): void
    {
        $meminfo = $this->meminfo
        (
            "MemTotal:        3999999 kB\n" .
            "MemFree:          100000 kB\n" .
            "MemAvailable:       2048 kB\n" .
            "Buffers:            1234 kB\n"
        ) ;

        $this->assertSame( 2048 * 1024 , availableMemoryBytes( '-1' , $meminfo ) ) ;
    }

    public function testMemAvailableIsReadWhateverItsCase(): void
    {
        $meminfo = $this->meminfo( "memavailable:       4096 KB\n" ) ;

        $this->assertSame( 4096 * 1024 , availableMemoryBytes( '-1' , $meminfo ) ) ;
    }

    /**
     * No `memory_limit`, no `/proc/meminfo`: this is a Mac, or a hardened container.
     * `null` says *unknown*, and the caller must let the operation through rather
     * than refuse on a number nobody has.
     */
    public function testUnlimitedWithoutMemInfoGivesNull(): void
    {
        $this->assertNull( availableMemoryBytes( '-1' , $this->testDir . '/absent' ) ) ;
    }

    public function testUnlimitedWithMemInfoLackingMemAvailableGivesNull(): void
    {
        $meminfo = $this->meminfo( "MemTotal:        3999999 kB\nMemFree:          100000 kB\n" ) ;

        $this->assertNull( availableMemoryBytes( '-1' , $meminfo ) ) ;
    }

    public function testUnreadableMemoryLimitValueFallsBackToMemAvailable(): void
    {
        $meminfo = $this->meminfo( "MemAvailable:       8192 kB\n" ) ;

        $this->assertSame( 8192 * 1024 , availableMemoryBytes( 'plenty' , $meminfo ) ) ;
    }

    /**
     * The default argument: with no value passed, the ini setting is read. Asserted
     * against the same function fed that setting, so the test says the same thing on
     * a runner with a limit and on one without.
     */
    public function testDefaultsToTheIniSetting(): void
    {
        $meminfo = $this->meminfo( "MemAvailable:      16384 kB\n" ) ;

        $this->assertSame
        (
            availableMemoryBytes( (string) ini_get('memory_limit') , $meminfo ) ,
            availableMemoryBytes( null , $meminfo )
        ) ;
    }
}
