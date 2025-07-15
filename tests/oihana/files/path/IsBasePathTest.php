<?php

namespace oihana\files\path ;

use PHPUnit\Framework\TestCase;

final class IsBasePathTest extends TestCase
{
    public function testExactMatchReturnsTrue(): void
    {
        $this->assertTrue(isBasePath('/var/www', '/var/www'));
    }

    public function testSubDirectoryReturnsTrue(): void
    {
        $this->assertTrue(isBasePath('/var/www', '/var/www/site/index.php'));
    }

    public function testSiblingDirectoryReturnsFalse(): void
    {
        $this->assertFalse(isBasePath('/var/www', '/var/www-legacy'));
    }

    public function testNestedWindowsPath(): void
    {
        $this->assertTrue(isBasePath('C:\\Users', 'C:\\Users\\Bob\\file.txt'));
    }

    public function testCaseSensitiveWindowsFalse(): void
    {
        // Behaviour on Windows: case sensitive in current implementation
        $this->assertFalse(isBasePath('C:/Users', 'c:/users/Bob'));
    }
}