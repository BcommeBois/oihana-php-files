<?php

namespace tests\oihana\files\enums ;

use oihana\files\enums\FileSizeUnit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FileSizeUnit::class)]
class FileSizeUnitTest extends TestCase
{
    public function testOrderedReturnsAscendingMagnitudes(): void
    {
        $this->assertSame
        (
            [
                FileSizeUnit::B ,
                FileSizeUnit::KB ,
                FileSizeUnit::MB ,
                FileSizeUnit::GB ,
                FileSizeUnit::TB ,
                FileSizeUnit::PB ,
            ],
            FileSizeUnit::ordered()
        );
    }

    public function testIndexMatchesPowerOf1024(): void
    {
        $units = FileSizeUnit::ordered();

        $this->assertSame( FileSizeUnit::B  , $units[ 0 ] );
        $this->assertSame( FileSizeUnit::KB , $units[ 1 ] );
        $this->assertSame( FileSizeUnit::PB , $units[ 5 ] );
    }
}
