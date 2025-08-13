<?php

namespace oihana\options;

use oihana\reflect\traits\ConstantsTrait;

use function oihana\core\strings\hyphenate;

/**
 * Abstract base class for mapping property names to command-line option names.
 *
 * Typically used with {@see Options::getOptions()} to convert property keys
 * into hyphenated command-line flags (e.g. "dryRun" → "dry-run").
 */
abstract class Option
{
    use ConstantsTrait ;

    /**
     * Returns the command line option expression from a specific option.
     * @param string $option The name of the option to modify.
     * @return string
     */
    public static function getCommandOption( string $option ):string
    {
        return hyphenate( $option ) ;
    }

    /**
     * Returns the prefix from a specific option.
     * @param string $option The name of the option.
     * @return ?string The prefix of the given option.
     */
    public static function getCommandPrefix( string $option ):?string
    {
        return null ;
    }
}