<?php

namespace oihana\files\path ;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversFunction('oihana\files\path\computeRelativePath')]
final class ComputeRelativePathTest extends TestCase
{
    public static function pathProvider(): array
    {
        return [
            // [expected, targetPath, basePath]
            'target below base'          => ['bar/baz'    , 'foo/bar/baz' , 'foo'    ],
            'sibling'                    => ['../baz'     , 'foo/baz'     , 'foo/bar'],
            'identical paths give dot'   => ['.'          , 'foo/bar'     , 'foo/bar'],
            'target is ancestor'         => ['../..'      , 'a/b'         , 'a/b/c/d'],
            'target deeper than base'    => ['b/c'        , 'a/b/c'       , 'a'      ],
            'diverging branches'         => ['../../x/y'  , 'a/x/y'       , 'a/b/c'  ],
            'empty parts are filtered'   => ['bar'        , 'foo//bar'    , 'foo'    ],
        ];
    }

    #[DataProvider('pathProvider')]
    public function testComputeRelativePath(string $expected, string $targetPath, string $basePath): void
    {
        $this->assertSame($expected, computeRelativePath($targetPath, $basePath));
    }
}
