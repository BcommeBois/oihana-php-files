<?php

namespace tests\oihana\files\openssl;

use PHPUnit\Framework\TestCase;

use function oihana\files\openssl\parseMemoryLimit;

class ParseMemoryLimitTest extends TestCase
{
    public function testPlainByteCount(): void
    {
        $this->assertSame( 134217728 , parseMemoryLimit( '134217728' ) ) ;
    }

    public function testKilobyteSuffix(): void
    {
        $this->assertSame( 1024 , parseMemoryLimit( '1K' ) ) ;
    }

    public function testMegabyteSuffix(): void
    {
        $this->assertSame( 268435456 , parseMemoryLimit( '256M' ) ) ;
    }

    public function testGigabyteSuffix(): void
    {
        $this->assertSame( 1073741824 , parseMemoryLimit( '1G' ) ) ;
    }

    public function testSuffixIsCaseInsensitive(): void
    {
        $this->assertSame( parseMemoryLimit( '512M' ) , parseMemoryLimit( '512m' ) ) ;
    }

    public function testSurroundingWhitespaceIsIgnored(): void
    {
        $this->assertSame( 268435456 , parseMemoryLimit( "  256 M \n" ) ) ;
    }

    /**
     * `-1` is PHP's *unlimited*: there is no ceiling to report, and reporting one
     * would be worse than reporting none.
     */
    public function testUnlimitedGivesNull(): void
    {
        $this->assertNull( parseMemoryLimit( '-1' ) ) ;
    }

    public function testZeroGivesNull(): void
    {
        $this->assertNull( parseMemoryLimit( '0' ) ) ;
    }

    public function testNegativeCountGivesNull(): void
    {
        $this->assertNull( parseMemoryLimit( '-512M' ) ) ;
    }

    public function testEmptyStringGivesNull(): void
    {
        $this->assertNull( parseMemoryLimit( '' ) ) ;
    }

    public function testNonNumericGivesNull(): void
    {
        $this->assertNull( parseMemoryLimit( 'plenty' ) ) ;
    }

    public function testUnknownSuffixGivesNull(): void
    {
        $this->assertNull( parseMemoryLimit( '128T' ) ) ;
    }

    /**
     * The one that must not be helpful. PHP reads `128MB` as 128 **bytes** — it takes
     * a single `k`, `m` or `g` as the multiplier and ignores the rest. Answering
     * 134217728 here would overstate the budget by a factor of a million, so the
     * answer is *unknown*.
     */
    public function testTrailingByteSuffixGivesNullRatherThanOverstatingTheBudget(): void
    {
        $this->assertNull( parseMemoryLimit( '128MB' ) ) ;
    }

    public function testEmbeddedWhitespaceGivesNull(): void
    {
        $this->assertNull( parseMemoryLimit( '2 5 6 M' ) ) ;
    }
}
