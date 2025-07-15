<?php

namespace oihana\files ;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use oihana\files\enums\FileExtension;

final class GetBaseFileNameTest extends TestCase
{
    /** Data set for simple extensions. */
    public static function simpleExtensionProvider(): array
    {
        return
        [
            [ 'photo.jpg'           , 'photo'  ] ,
            [ '/var/www/index.html' , 'index'  ] ,
            [ 'C:\Temp\report.PDF'  , 'report' ] ,
        ];
    }

    /** Default multi-part extension dataset (FileExtension). */
    public static function multipartDefaultProvider(): array
    {
        return
        [
            [ 'archive.tar.gz'          , 'archive' ] ,
            [ '/path/to/view.blade.php' , 'view'    ] ,
        ];
    }

    #[DataProvider('simpleExtensionProvider')]
    public function testReturnsNameWithoutSimpleExtension( string $input , string $expected ): void
    {
        self::assertSame( $expected , getBaseFileName( $input ) );
    }

    #[DataProvider('multipartDefaultProvider')]
    public function testHandlesDefaultMultipartExtensions(string $input, string $expected): void
    {
        // on utilise la liste par défaut de FileExtension + une extension custom > '.blade.php'
        self::assertSame( $expected, getBaseFileName( $input , FileExtension::getMultiplePartExtensions( [ '.blade.php'] ) ) );
    }

    /** Case: custom multipart passed as parameter. */
    public function testCustomMultipartExtensions(): void
    {
        $custom = [ '.test.csv' ];          // liste fournie par l’appelant
        $this->assertSame( 'dataset', getBaseFileName('dataset.test.csv' , $custom ) );
    }

    /** Case: no dot => return full name. */
    public function testNoExtension(): void
    {
        $this->assertSame('README', getBaseFileName('README'));
    }

    /** Case : Empty file path -> error. */
    public function testEmptyPathThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        getBaseFileName('');
    }

    /** Case : The path is a directory -> error */
    public function testDirectoryPathThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        // basename('path/to/dir/') renverra chaîne vide → exception
        getBaseFileName('/path/to/dir/');
    }
}