<?php

namespace tests\oihana\options\mocks;

use oihana\options\Options;

class TestOptions extends Options
{
    public ?string $host   = null;
    public ?int    $port   = null;
    public array   $flags  = [];
    public ?bool   $debug  = null;
}