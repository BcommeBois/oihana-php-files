<?php

namespace oihana\files ;

use PHPUnit\Framework\TestCase;

class ShouldExcludeFileTest extends TestCase
{
    public function testGlobPatternMatchesBasename(): void
    {
        $patterns = ['*.csv'];
        $this->assertTrue(shouldExcludeFile('/tmp/file.csv', $patterns));
        $this->assertFalse(shouldExcludeFile('/tmp/file.txt', $patterns));
    }

    public function testGlobPatternMatchesFullPath(): void
    {
        $patterns = ['/tmp/subdir/*.log'];
        $this->assertTrue(shouldExcludeFile('/tmp/subdir/error.log', $patterns));
        $this->assertFalse(shouldExcludeFile('/tmp/subdir/inner/error.log', $patterns));
    }

    public function testRegexPatternMatchesBasename(): void
    {
        $patterns = ['/^report_\d{4}\.xlsx$/i'];
        $this->assertTrue(shouldExcludeFile('/tmp/report_2023.xlsx', $patterns));
        $this->assertFalse(shouldExcludeFile('/tmp/summary_2023.xlsx', $patterns));
    }

    public function testRegexPatternMatchesFullPath(): void
    {
        $patterns = ['#/tmp/.*/error\.log#'];
        $this->assertTrue(shouldExcludeFile('/tmp/subdir/error.log', $patterns));
        $this->assertFalse(shouldExcludeFile('/tmp/otherdir/info.log', $patterns));
    }

    public function testMixedPatterns(): void
    {
        $patterns = ['*.csv', '/^report_\d{4}\.xlsx$/i'];

        $this->assertTrue(shouldExcludeFile('/tmp/file.csv', $patterns));
        $this->assertTrue(shouldExcludeFile('/tmp/report_2025.xlsx', $patterns));
        $this->assertFalse(shouldExcludeFile('/tmp/file.txt', $patterns));
    }

    public function testEmptyPatterns(): void
    {
        $this->assertFalse(shouldExcludeFile('/tmp/file.csv', []));
    }
}