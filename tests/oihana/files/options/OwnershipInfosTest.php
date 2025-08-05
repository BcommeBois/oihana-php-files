<?php

namespace oihana\files\options ;

use oihana\files\enums\OwnershipInfo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OwnershipInfos::class)]
class OwnershipInfosTest extends TestCase
{
    public function testCanBeInstantiatedWithProperties(): void
    {
        $info = new OwnershipInfos();
        $info->owner = 'www-data';
        $info->group = 'www-data';
        $info->uid   = 33;
        $info->gid   = 33;

        $this->assertSame('www-data', $info->owner);
        $this->assertSame('www-data', $info->group);
        $this->assertSame(33, $info->uid);
        $this->assertSame(33, $info->gid);
    }

    public function testCanBeInstantiatedFromArray(): void
    {
        $info = new OwnershipInfos
        ([
            OwnershipInfo::OWNER => 'root',
            OwnershipInfo::GROUP => 'staff',
            OwnershipInfo::UID   => 0,
            OwnershipInfo::GID   => 20,
        ]);

        $this->assertSame('root', $info->owner);
        $this->assertSame('staff', $info->group);
        $this->assertSame(0, $info->uid);
        $this->assertSame(20, $info->gid);
    }

    public function testEqualsToReturnsTrueForIdenticalObjects(): void
    {
        $a = new OwnershipInfos
        ([
            'owner' => 'user',
            'group' => 'staff',
            'uid'   => 501,
            'gid'   => 20,
        ]);

        $b = new OwnershipInfos
        ([
            'owner' => 'user',
            'group' => 'staff',
            'uid'   => 501,
            'gid'   => 20,
        ]);

        $this->assertTrue($a->equalsTo($b));
    }

    public function testEqualsToReturnsFalseForDifferentObjects(): void
    {
        $a = new OwnershipInfos([
            'owner' => 'user1',
            'group' => 'staff',
            'uid'   => 501,
            'gid'   => 20,
        ]);

        $b = new OwnershipInfos([
            'owner' => 'user2',
            'group' => 'staff',
            'uid'   => 502,
            'gid'   => 20,
        ]);

        $this->assertFalse($a->equalsTo($b));
    }

    public function testToStringReturnsExpectedFormat(): void
    {
        $info = new OwnershipInfos([
            'owner' => 'www-data',
            'group' => 'www-data',
            'uid'   => 33,
            'gid'   => 33,
        ]);

        $this->assertSame('www-data:www-data (33:33)', (string) $info ) ;
    }

    public function testToStringHandlesNullValuesGracefully(): void
    {
        $info = new OwnershipInfos();
        $this->assertSame('?:? (?:?)', (string) $info ) ;
    }
}