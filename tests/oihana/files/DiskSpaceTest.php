<?php

namespace tests\oihana\files ;

use PHPUnit\Framework\TestCase;

use oihana\files\exceptions\FileException;

use function oihana\files\getDiskUsage;
use function oihana\files\getFreeDiskSpace;
use function oihana\files\getTotalDiskSpace;

class DiskSpaceTest extends TestCase
{
    private string $missing = '/this/path/does/not/exist/oihana-disk-test';

    /**
     * @throws FileException
     */
    public function testFreeDiskSpaceIsPositive(): void
    {
        $this->assertGreaterThan( 0 , getFreeDiskSpace( sys_get_temp_dir() ) );
    }

    /**
     * @throws FileException
     */
    public function testTotalDiskSpaceIsPositive(): void
    {
        $this->assertGreaterThan( 0 , getTotalDiskSpace( sys_get_temp_dir() ) );
    }

    /**
     * @throws FileException
     */
    public function testUsageIsTotalMinusFreeAndWithinBounds(): void
    {
        $dir   = sys_get_temp_dir();
        $total = getTotalDiskSpace( $dir );
        $free  = getFreeDiskSpace( $dir );
        $used  = getDiskUsage( $dir );

        $this->assertSame( $total - $free , $used );
        $this->assertGreaterThanOrEqual( 0 , $used );
        $this->assertLessThanOrEqual( $total , $used );
    }

    public function testFreeDiskSpaceThrowsOnInvalidPath(): void
    {
        $this->expectException( FileException::class );
        $this->expectExceptionMessage( 'free disk space' );
        getFreeDiskSpace( $this->missing );
    }

    public function testTotalDiskSpaceThrowsOnInvalidPath(): void
    {
        $this->expectException( FileException::class );
        $this->expectExceptionMessage( 'total disk space' );
        getTotalDiskSpace( $this->missing );
    }

    public function testDiskUsageThrowsOnInvalidPath(): void
    {
        $this->expectException( FileException::class );
        getDiskUsage( $this->missing );
    }
}
