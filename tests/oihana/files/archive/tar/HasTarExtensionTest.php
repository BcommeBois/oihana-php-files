<?php

namespace tests\oihana\files\archive\tar;

use oihana\files\enums\FileExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use function oihana\files\archive\tar\hasTarExtension;

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
            ['data.tar.xz'         , true  ] , // written by the system tar since 1.5.0
            ['data.txz'            , true  ] ,
            ['data.tar.zst'        , true  ] ,
            ['data.tar.lzma'       , false ] , // a format this library does not write
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
        $this->assertTrue( hasTarExtension( $filename, $customExtensions ) );
    }
}