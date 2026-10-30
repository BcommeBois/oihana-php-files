<?php

namespace oihana\files\toml ;

/**
 * Escapes a value so it can be embedded in a TOML basic string.
 *
 * The returned value does **not** include the surrounding quotes. Backslashes and double
 * quotes are escaped, and the control characters TOML forbids in a basic string are written
 * as escape sequences: the ones with a shorthand (`\b`, `\t`, `\n`, `\f`, `\r`) use it, the
 * others and DEL become `\uXXXX`.
 *
 * Without this, a value holding a quote or a backslash — a password, typically — produces a
 * document the TOML parser rejects, and the whole file becomes unreadable.
 *
 * @param string $value The raw value to escape.
 *
 * @return string The escaped value, ready to be wrapped in double quotes.
 *
 * @see https://toml.io/en/v1.0.0#string
 *
 * @example
 * ```php
 * use function oihana\files\toml\escapeTomlString;
 *
 * echo escapeTomlString( 'pa"ss\word' ) ; // pa\"ss\\word
 * echo escapeTomlString( "two\nlines" ) ; // two\nlines
 * echo escapeTomlString( "\x01" ) ;       // \u0001
 * ```
 *
 * @package oihana\files\toml
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.7.0
 */
function escapeTomlString( string $value ) :string
{
    $escaped = strtr
    (
        $value ,
        [
            '\\'   => '\\\\' ,
            '"'    => '\\"'  ,
            "\x08" => '\\b'  ,
            "\t"   => '\\t'  ,
            "\n"   => '\\n'  ,
            "\x0C" => '\\f'  ,
            "\r"   => '\\r'  ,
        ]
    ) ;

    // The other C0 controls and DEL have no shorthand: written as \uXXXX.

    return preg_replace_callback
    (
        '/[\x00-\x07\x0B\x0E-\x1F\x7F]/' ,
        static fn( array $match ) :string => sprintf( '\\u%04X' , ord( $match[ 0 ] ) ) ,
        $escaped
    ) ;
}
