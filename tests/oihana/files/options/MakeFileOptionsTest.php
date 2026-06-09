<?php

namespace tests\oihana\files\options ;

use oihana\files\options\MakeFileOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MakeFileOptions::class)]
class MakeFileOptionsTest extends TestCase
{
    public function testToStringReturnsFilePath(): void
    {
        $options = new MakeFileOptions();
        $options->file = '/tmp/robots.txt';
        $this->assertSame('/tmp/robots.txt', (string) $options);
    }

    public function testToStringReturnsEmptyStringWhenFileIsNull(): void
    {
        $options = new MakeFileOptions();
        $options->file = null;
        $this->assertSame('', (string) $options);
    }
}
