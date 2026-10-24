<?php

namespace tests\oihana\files\archive\tar;

use PHPUnit\Framework\TestCase;

use function oihana\files\archive\tar\tarProcessEnvironment;

/**
 * Two variables, and the second one was missing for two releases.
 *
 * `proc_open()` given an environment **replaces** the whole of it. `tar` compresses
 * nothing itself — `--xz` makes it spawn `xz` through a shell — so with no `PATH` that
 * shell fell back to its built-in default and found `gzip` and `bzip2` under `/usr/bin`
 * while missing anything installed elsewhere. It went unnoticed because `tar()` refused
 * `xz` before ever reaching the binary.
 */
class TarProcessEnvironmentTest extends TestCase
{
    private string|false $path ;

    protected function setUp() :void
    {
        $this->path = getenv( 'PATH' ) ;
    }

    protected function tearDown() :void
    {
        putenv( $this->path === false ? 'PATH' : 'PATH=' . $this->path ) ;
    }

    /**
     * `tar -tv` prints a date column, and the listing reads the member name as everything
     * after the time. A locale that spells dates differently would break that parse on a
     * sound archive.
     */
    public function testTheLocaleIsAlwaysPinned(): void
    {
        $this->assertSame( 'C' , tarProcessEnvironment()[ 'LC_ALL' ] ) ;
    }

    public function testThePathIsPassedThrough(): void
    {
        putenv( 'PATH=/opt/somewhere/bin:/usr/bin' ) ;

        $this->assertSame( '/opt/somewhere/bin:/usr/bin' , tarProcessEnvironment()[ 'PATH' ] ) ;
    }

    /**
     * No `PATH` to pass on is not the same as an empty one: an empty `PATH` would stop the
     * child's shell falling back to its own default, which is the only thing left to try.
     */
    public function testNoPathMeansNoPathVariable(): void
    {
        putenv( 'PATH' ) ;

        $environment = tarProcessEnvironment() ;

        $this->assertArrayNotHasKey( 'PATH' , $environment ) ;
        $this->assertSame( [ 'LC_ALL' => 'C' ] , $environment ) ;
    }
}
