<?php

namespace tests\oihana\options\mocks;

use oihana\options\Option;

class MockOption extends Option
{
    public static function getCommandOption(string $option): string
    {
        return str_replace('_', '-', $option);
    }
}