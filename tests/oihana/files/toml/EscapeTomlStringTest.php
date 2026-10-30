<?php

namespace tests\oihana\files\toml;

use Devium\Toml\Toml;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function oihana\files\toml\escapeTomlString;

#[CoversFunction('oihana\files\toml\escapeTomlString')]
final class EscapeTomlStringTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function cases() :array
    {
        return
        [
            'plain text is left alone' => [ 'plain value'   , 'plain value'     ] ,
            'a double quote'           => [ 'a"b'           , 'a\\"b'           ] ,
            'a backslash'              => [ 'a\\b'          , 'a\\\\b'          ] ,
            'a single quote stays'     => [ "it's"          , "it's"            ] ,
            'backspace'                => [ "\x08"          , '\\b'             ] ,
            'tab'                      => [ "\t"            , '\\t'             ] ,
            'line feed'                => [ "\n"            , '\\n'             ] ,
            'form feed'                => [ "\x0C"          , '\\f'             ] ,
            'carriage return'          => [ "\r"            , '\\r'             ] ,
            'NUL, without a shorthand' => [ "\x00"          , '\\u0000'         ] ,
            'another C0 control'       => [ "\x1F"          , '\\u001F'         ] ,
            'DEL'                      => [ "\x7F"          , '\\u007F'         ] ,
            'non-ASCII stays as is'    => [ 'été'           , 'été'             ] ,
            'a backreference stays'    => [ 'pa$1ss'        , 'pa$1ss'          ] ,
        ] ;
    }

    #[DataProvider('cases')]
    public function testEscapes( string $raw , string $expected ) :void
    {
        $this->assertSame( $expected , escapeTomlString( $raw ) ) ;
    }

    /**
     * The point of the function: whatever goes in, a TOML parser reads back the same value.
     */
    public function testATomlParserReadsTheValueBack() :void
    {
        $raw = "q\"b\\s\x08t\tn\nf\x0Cr\rnul\x00del\x7Fé" ;

        $document = Toml::decode( 'value = "' . escapeTomlString( $raw ) . '"' , true ) ;

        $this->assertSame( $raw , $document[ 'value' ] ) ;
    }
}
