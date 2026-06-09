<?php

namespace oihana\files ;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\TestCase;

#[CoversFunction('oihana\files\isLinux')]
#[CoversFunction('oihana\files\isMac')]
#[CoversFunction('oihana\files\isWindows')]
#[CoversFunction('oihana\files\isOtherOS')]
final class OsTest extends TestCase
{
    /**
     * Each predicate must agree with the runtime PHP_OS constant.
     */
    public function testEachMatchesPhpOs(): void
    {
        $this->assertSame( strtoupper( substr( PHP_OS , 0 , 5 ) ) === 'LINUX'  , isLinux()   );
        $this->assertSame( strtoupper( substr( PHP_OS , 0 , 6 ) ) === 'DARWIN' , isMac()     );
        $this->assertSame( strtoupper( substr( PHP_OS , 0 , 3 ) ) === 'WIN'    , isWindows() );
        $this->assertSame( !isWindows() && !isLinux() && !isMac()              , isOtherOS() );
    }

    /**
     * The four families are mutually exclusive and exhaustive: exactly one is true.
     */
    public function testExactlyOneFamilyIsTrue(): void
    {
        $flags = [ isLinux() , isMac() , isWindows() , isOtherOS() ];
        $this->assertSame( 1 , count( array_filter( $flags ) ) , 'Exactly one OS predicate must be true.' );
    }

    /**
     * A second call hits the static-cache branch and must return the same value.
     */
    public function testResultsAreMemoizedAndStable(): void
    {
        $this->assertSame( isLinux()   , isLinux()   );
        $this->assertSame( isMac()     , isMac()     );
        $this->assertSame( isWindows() , isWindows() );
        $this->assertSame( isOtherOS() , isOtherOS() );
    }
}
