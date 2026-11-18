<?php

namespace tests\oihana\options;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

use function oihana\options\formatOptionValue;

#[CoversFunction('oihana\options\formatOptionValue')]
final class FormatOptionValueTest extends TestCase
{
    /**
     * @return array<string, array{mixed, string}>
     */
    public static function cases() :array
    {
        $object = new stdClass() ;
        $object->name = 'Café' ;

        return
        [
            'a string, between single quotes' => [ 'value'               , "'value'"                 ] ,
            'an empty string'                 => [ ''                    , "''"                      ] ,
            'a dollar is not expanded'        => [ 'a$HOME'              , "'a\$HOME'"               ] ,
            'backquotes run nothing'          => [ 'a`id`'               , "'a`id`'"                 ] ,
            'a single quote'                  => [ "it's"                , "'it'\\''s'"              ] ,
            'accents stay as they are'        => [ 'Café'                , "'Café'"                  ] ,
            'a slash stays as it is'          => [ '/var/www'            , "'/var/www'"              ] ,
            'an integer, bare'                => [ 8080                  , '8080'                    ] ,
            'a float, bare'                   => [ 1.5                   , '1.5'                     ] ,
            'false, bare'                     => [ false                 , 'false'                   ] ,
            'null, bare'                      => [ null                  , 'null'                    ] ,
            'a nested array, quoted JSON'     => [ [ 'a' => 'é/b' ]      , "'{\"a\":\"é/b\"}'"       ] ,
            'an object, quoted JSON'          => [ $object               , "'{\"name\":\"Café\"}'"   ] ,
        ] ;
    }

    #[DataProvider('cases')]
    public function testFormats( mixed $value , string $expected ) :void
    {
        $this->assertSame( $expected , formatOptionValue( $value ) ) ;
    }

    public function testANulByteIsRefused() :void
    {
        $this->expectException( InvalidArgumentException::class ) ;

        formatOptionValue( "a\0b" ) ;
    }
}
