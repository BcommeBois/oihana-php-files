<?php

namespace tests\oihana\options\mocks;

use oihana\interfaces\Arrayable;

/**
 * A minimal source implementing the plain {@see Arrayable} interface (no clear flag),
 * used to exercise the Arrayable branch of Options::resolve().
 */
class ArrayableSource implements Arrayable
{
    public function toArray(): array
    {
        return ['host' => 'arrayable'];
    }
}
