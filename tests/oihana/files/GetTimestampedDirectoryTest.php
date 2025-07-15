<?php

namespace oihana\files ;

use oihana\files\exceptions\DirectoryException;
use PHPUnit\Framework\TestCase;

class GetTimestampedDirectoryTest extends TestCase
{
    /**
     * @throws DirectoryException
     */
    public function testReturnsStringPathWithDefaults()
    {
        $dir = getTimestampedDirectory(assertable: false);
        $this->assertIsString($dir);
        $this->assertMatchesRegularExpression(
            '/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/',
            $dir,
            'The directory name should end with a timestamp in Y-m-d\TH:i:s format'
        );
    }

    /**
     * @throws DirectoryException
     */
    public function testPrefixAndSuffixAreApplied()
    {
        $prefix = 'pre_';
        $suffix = '_suf';
        $dir = getTimestampedDirectory(prefix: $prefix, suffix: $suffix, assertable: false);
        $this->assertStringStartsWith($prefix, basename($dir));
        $this->assertStringEndsWith($suffix, basename($dir));
    }

    /**
     * @throws DirectoryException
     */
    public function testCustomDateAndFormat()
    {
        $date = '2025-12-01 14:00:00';
        $format = 'Ymd_His';
        $dir = getTimestampedDirectory(date: $date, format: $format, assertable: false);
        $this->assertStringContainsString('20251201_140000', basename($dir));
    }

    /**
     * @throws DirectoryException
     */
    public function testBasePathIsPrepended()
    {
        $basePath = '/tmp';
        $dir = getTimestampedDirectory(basePath: $basePath, assertable: false);
        $this->assertStringStartsWith($basePath . DIRECTORY_SEPARATOR, $dir);
    }

    public function testAssertionThrowsExceptionWhenInvalid()
    {
        $this->expectException(DirectoryException::class);
        getTimestampedDirectory
        (
            basePath: '/path/that/does/not/exist',
            assertable: true
        );
    }

    /**
     * @throws DirectoryException
     */
    public function testNoAssertionWhenAssertableFalse()
    {
        $basePath = '/path/that/does/not/exist';
        $dir = getTimestampedDirectory(basePath: $basePath, assertable: false);
        $this->assertStringContainsString($basePath, $dir);
    }
}