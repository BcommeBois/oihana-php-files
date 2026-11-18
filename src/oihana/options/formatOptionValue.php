<?php

namespace oihana\options ;

use InvalidArgumentException;

use function oihana\core\strings\escapeShellArgument;

/**
 * Writes the value of a command-line option, as {@see Options::getOptions()} places it after
 * the option's name.
 *
 * - A string goes between single quotes (`escapeShellArgument()` of oihana/php-core): the shell
 *   passes it to the program exactly as it is, `$`, backquotes, backslashes and accented letters
 *   included.
 * - An integer, a float, `false` and `null` are written bare, as their JSON form: `8080`, `1.5`,
 *   `false`, `null`.
 * - Anything else (a nested array, an object) is written as its JSON form, between single quotes.
 *
 * Strings used to be written between double quotes by `json_encode()`, where the shell still
 * expands `$` and runs backquotes, and where `é` became the six characters `\u00e9`.
 *
 * @param mixed $value The option's value.
 *
 * @return string The value, ready to follow the option's name on a shell command line.
 *
 * @throws InvalidArgumentException If a string holds a NUL byte.
 *
 * @example
 * ```php
 * use function oihana\options\formatOptionValue;
 *
 * echo formatOptionValue( 'Café' ) ;       // 'Café'
 * echo formatOptionValue( 'a$HOME' ) ;     // 'a$HOME'
 * echo formatOptionValue( 8080 ) ;         // 8080
 * echo formatOptionValue( false ) ;        // false
 * echo formatOptionValue( [ 'a' => 1 ] ) ; // '{"a":1}'
 * ```
 *
 * @package oihana\options
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.7.0
 */
function formatOptionValue( mixed $value ) :string
{
    if( is_string( $value ) )
    {
        return escapeShellArgument( $value ) ;
    }

    $json = (string) json_encode( $value , JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ;

    if( is_int( $value ) || is_float( $value ) || is_bool( $value ) || $value === null )
    {
        return $json ;
    }

    return escapeShellArgument( $json ) ;
}
