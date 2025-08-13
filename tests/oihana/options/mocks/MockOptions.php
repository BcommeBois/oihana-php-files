<?php

namespace tests\oihana\options\mocks;

use oihana\options\Options;

class MockOptions extends Options
{
    public string $foo = '' ;
    public bool   $bar = false ;
    public array  $baz = [] ;

    public ?string $alpha ;
    public ?string $zeta ;

    public ?string $domain ;
    public ?string $subdomain ;

    public function __toString(): string
    {
        return 'OptionsToString';
    }
}