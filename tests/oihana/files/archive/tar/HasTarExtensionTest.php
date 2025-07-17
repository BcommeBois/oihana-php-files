<?php

namespace oihana\files\archive\tar;

use oihana\files\enums\FileExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HasTarExtensionTest extends TestCase
{
    public static function tarFileProvider(): array
    {
        return
        [
            ['archive.tar'         , true  ] ,
            ['backup.tgz'          , true  ] ,
            ['logs.gz'             , true  ] ,
            ['data.tar.gz'         , true  ] ,
            ['compressed.tar.bz2'  , true  ] ,
            ['file.bz2'            , true  ] ,
            ['archive.TAR'         , true  ] , // case insensitivity
            ['file.TAR.GZ'         , true  ] , // uppercase double extension
            ['file.zip'            , false ] ,
            ['archive.rar'         , false ] ,
            ['data.tar.xz'         , false ] , // not in default list
            ['just.a.file'         , false ] ,
        ];
    }

    #[DataProvider('tarFileProvider')]
    public function testHasTarExtension(string $filename, bool $expected)
    {
        $this->assertSame($expected, hasTarExtension($filename));
    }

    public function testCustomTarExtensions()
    {
        $filename = 'example.tar.xz';
        $customExtensions =
        [
            FileExtension::TAR,
            '.tar.xz' // custom extension added
        ];
        $this->assertTrue(hasTarExtension($filename, $customExtensions));
    }
}