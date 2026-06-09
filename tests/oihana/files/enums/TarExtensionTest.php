<?php

namespace tests\oihana\files\enums ;

use oihana\files\enums\CompressionType;
use oihana\files\enums\FileExtension;
use oihana\files\enums\TarExtension;
use oihana\files\exceptions\UnsupportedCompressionException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TarExtension::class)]
class TarExtensionTest extends TestCase
{
    public function testGetExtensionForCompressionGzip(): void
    {
        $this->assertSame(FileExtension::TAR_GZ, TarExtension::getExtensionForCompression(CompressionType::GZIP));
    }

    public function testGetExtensionForCompressionBzip2(): void
    {
        $this->assertSame(FileExtension::TAR_BZ2, TarExtension::getExtensionForCompression(CompressionType::BZIP2));
    }

    public function testGetExtensionForCompressionNone(): void
    {
        $this->assertSame(FileExtension::TAR, TarExtension::getExtensionForCompression(CompressionType::NONE));
    }

    public function testGetExtensionForCompressionThrowsOnUnsupported(): void
    {
        $this->expectException(UnsupportedCompressionException::class);
        $this->expectExceptionMessage("Compression type 'zip' is not supported");

        TarExtension::getExtensionForCompression(CompressionType::ZIP);
    }
}
