<?php

namespace oihana\files ;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversFunction('oihana\files\getSchemeAndHierarchy')]
final class GetSchemaAndHierarchyTest extends TestCase
{
    #[DataProvider('provideValidInputs')]
    public function testReturnsExpectedTuple(string $input, array $expected): void
    {
        $this->assertSame($expected, getSchemeAndHierarchy($input) ) ;
    }

    public static function provideValidInputs(): array
    {
        return
        [
            'local‑path'    => [ '/var/www/html',        [ null      , '/var/www/html'     ] ] ,
            'windows‑path'  => [ 'C:\\data\\log.txt',    [ null      , 'C:\\data\\log.txt' ] ] ,
            'file‑uri'      => [ 'file:///tmp/app.log',  [ 'file'    , '/tmp/app.log'      ] ] ,
            's3‑uri'        => [ 's3://bucket/key',      [ 's3'      , 'bucket/key'        ] ] ,
            'custom‑scheme' => [ 'git+ssh://repo/path',  [ 'git+ssh' , 'repo/path'         ] ] ,
            'empty‑scheme'  => [ '://just/path',         [ null      , 'just/path'         ] ] ,
        ];
    }


    public function testRejectsMalformedScheme(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        getSchemeAndHierarchy('1http://example.com'); // scheme must not start with a digit
    }
}