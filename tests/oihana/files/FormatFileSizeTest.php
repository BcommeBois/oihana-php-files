<?php

namespace tests\oihana\files ;

use PHPUnit\Framework\TestCase;

use function oihana\files\formatFileSize;

class FormatFileSizeTest extends TestCase
{
    public function testZeroBytes(): void
    {
        $this->assertSame( '0 B' , formatFileSize( 0 ) );
    }

    public function testNegativeValueYieldsZero(): void
    {
        $this->assertSame( '0 B' , formatFileSize( -10 ) );
    }

    public function testBytesHaveNoDecimals(): void
    {
        $this->assertSame( '512 B' , formatFileSize( 512 ) );
        $this->assertSame( '1023 B' , formatFileSize( 1023 ) );
    }

    public function testKilobytes(): void
    {
        $this->assertSame( '1 KB'   , formatFileSize( 1024 ) );
        $this->assertSame( '1.5 KB' , formatFileSize( 1536 ) );
    }

    public function testMegabytes(): void
    {
        $this->assertSame( '1.18 MB' , formatFileSize( 1240518 ) );
    }

    public function testGigabytes(): void
    {
        $this->assertSame( '2 GB' , formatFileSize( 2 * ( 1024 ** 3 ) ) );
    }

    public function testRespectsPrecision(): void
    {
        $this->assertSame( '1.183 MB' , formatFileSize( 1240518 , 3 ) );
    }

    public function testClampsAbovePetabytes(): void
    {
        // 1024^6 would be EB; clamped to PB.
        $this->assertSame( '1024 PB' , formatFileSize( 1024 ** 6 ) );
    }
}
