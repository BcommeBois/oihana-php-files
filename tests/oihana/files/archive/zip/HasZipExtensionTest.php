<?php

namespace tests\oihana\files\archive\zip;

use oihana\files\enums\FileExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function oihana\files\archive\zip\hasZipExtension;

class HasZipExtensionTest extends TestCase
{
    public static function zipFileProvider(): array
    {
        return
        [
            [ 'archive.zip'  , true  ] ,
            [ 'ARCHIVE.ZIP'  , true  ] , // case insensitivity
            [ 'photos.Zip'   , true  ] ,
            [ 'archive.tar'  , false ] ,
            [ 'data.tar.gz'  , false ] ,
            [ 'file.rar'     , false ] ,
            [ 'just.a.file'  , false ] ,
            [ 'noextension'  , false ] ,
        ];
    }

    #[DataProvider('zipFileProvider')]
    public function testHasZipExtension( string $filename , bool $expected ): void
    {
        $this->assertSame( $expected , hasZipExtension( $filename ) );
    }

    public function testCustomZipExtensions(): void
    {
        $filename = 'example.zipx';
        $customExtensions =
        [
            FileExtension::ZIP ,
            '.zipx' // custom extension added
        ];
        $this->assertTrue( hasZipExtension( $filename , $customExtensions ) );
    }
}
