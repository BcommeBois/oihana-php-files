<?php

namespace oihana\files ;

use oihana\files\enums\OwnershipInfo;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversFunction('oihana\files\getOwnershipInfo')]
final class GetOwnershipInfoTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = tempnam( sys_get_temp_dir(), '/oihana/tests/');
        file_put_contents($this->tmpFile, 'test');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    public function testReturnsOwnershipInfoObject(): void
    {
        $info = getOwnershipInfo($this->tmpFile);
        $this->assertIsInt($info->uid);
        $this->assertIsInt($info->gid);
    }

    public function testResolvesOwnerAndGroupNamesWhenPosixAvailable(): void
    {
        if (!function_exists('posix_getpwuid') || !function_exists('posix_getgrgid'))
        {
            $this->markTestSkipped('POSIX functions not available');
        }

        $info = getOwnershipInfo($this->tmpFile);

        $this->assertNotNull($info->owner, 'Expected owner name to be resolved');
        $this->assertNotNull($info->group, 'Expected group name to be resolved');
        $this->assertIsString($info->owner);
        $this->assertIsString($info->group);
    }

    public function testThrowsExceptionOnInvalidPath(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Path '/does/not/exist' does not exist.");

        getOwnershipInfo('/does/not/exist');
    }

    public function testToStringMethod(): void
    {
        $info = getOwnershipInfo($this->tmpFile);

        $str = (string) $info;
        $this->assertMatchesRegularExpression('/.+:.+ \(\d+:\d+\)/', $str);
    }

    public function testEqualsToReturnsTrueForSameOwnership(): void
    {
        $info1 = getOwnershipInfo($this->tmpFile);
        $info2 = getOwnershipInfo($this->tmpFile);

        $this->assertTrue($info1->equalsTo($info2));
    }

    public function testEqualsToReturnsFalseForDifferentOwnership(): void
    {
        $info1 = new OwnershipInfo([
            OwnershipInfo::UID   => 1000,
            OwnershipInfo::GID   => 1000,
            OwnershipInfo::OWNER => 'user1',
            OwnershipInfo::GROUP => 'group1',
        ]);

        $info2 = new OwnershipInfo([
            OwnershipInfo::UID   => 2000,
            OwnershipInfo::GID   => 2000,
            OwnershipInfo::OWNER => 'user2',
            OwnershipInfo::GROUP => 'group2',
        ]);

        $this->assertFalse($info1->equalsTo($info2));
    }
}