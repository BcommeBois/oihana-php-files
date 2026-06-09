<?php

namespace tests\oihana\options ;

use oihana\options\Option;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Option::class)]
class OptionTest extends TestCase
{
    public function testGetCommandOptionHyphenatesByDefault(): void
    {
        $this->assertSame('dry-run', Option::getCommandOption('dryRun'));
    }
}
