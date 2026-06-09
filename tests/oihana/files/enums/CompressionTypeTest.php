<?php

namespace tests\oihana\files\enums ;

use oihana\files\enums\CompressionType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CompressionType::class)]
class CompressionTypeTest extends TestCase
{
    public function testGetDefaultReturnsGzip(): void
    {
        $this->assertSame(CompressionType::GZIP, CompressionType::getDefault());
    }

    public function testGetFastCompressionTypes(): void
    {
        $this->assertSame
        (
            [ CompressionType::NONE, CompressionType::LZ4, CompressionType::ZSTD ],
            CompressionType::getFastCompressionTypes()
        );
    }

    public function testGetHighRatioCompressionTypes(): void
    {
        $this->assertSame
        (
            [ CompressionType::LZMA, CompressionType::XZ, CompressionType::BZIP2 ],
            CompressionType::getHighRatioCompressionTypes()
        );
    }
}
