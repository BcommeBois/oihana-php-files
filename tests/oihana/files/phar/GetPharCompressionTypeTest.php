<?php

namespace tests\oihana\files\phar ;

use oihana\files\enums\CompressionType;
use oihana\files\exceptions\UnsupportedCompressionException;
use Phar;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\TestCase;

use function oihana\files\phar\getPharCompressionType;

#[CoversFunction('oihana\files\phar\getPharCompressionType')]
class GetPharCompressionTypeTest extends TestCase
{
    public function testGzip(): void
    {
        $this->assertSame(Phar::GZ, getPharCompressionType(CompressionType::GZIP));
    }

    public function testBzip2(): void
    {
        $this->assertSame(Phar::BZ2, getPharCompressionType(CompressionType::BZIP2));
    }

    public function testNone(): void
    {
        $this->assertSame(Phar::NONE, getPharCompressionType(CompressionType::NONE));
    }

    public function testThrowsOnUnsupportedCompression(): void
    {
        $this->expectException(UnsupportedCompressionException::class);
        $this->expectExceptionMessage("Compression type 'zip' is not supported");

        getPharCompressionType(CompressionType::ZIP);
    }
}
