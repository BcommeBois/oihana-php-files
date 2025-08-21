<?php

namespace oihana\options;

use oihana\reflect\traits\ConstantsTrait;

use function oihana\core\strings\hyphenate;

/**
 * Abstract base class that maps public property names to command-line option names.
 *
 * This class is used by {@see Options::getOptions()} to transform the keys of the public
 * properties of a class extending {@see Options} into CLI flags. By default, names are
 * hyphenated via {@see hyphenate()} (e.g., "dryRun" → "dry-run").
 *
 * Typical customization:
 * - Override {@see Option::getCommandOption()} to customize the option name.
 * - Override {@see Option::getCommandPrefix()} to provide a per-option prefix
 *   (e.g., "--", "-", "/opt:", etc.).
 *
 * Minimal usage example with Options::getOptions():
 *
 * ```php
 * use oihana\options\Option;
 * use oihana\options\Options;
 *
 * class MyOption extends Option
 * {
 *     // Optional: customize the name transformation
 *     public static function getCommandOption(string $option): string
 *     {
 *         return parent::getCommandOption($option); // default: hyphenate
 *     }
 *
 *     // Optional: set a prefix depending on the option name
 *     public static function getCommandPrefix(string $option): ?string
 *     {
 *         return match ($option) {
 *             'verbose' => '-',
 *             default   => '--',
 *         };
 *     }
 * }
 *
 * class MyOptions extends Options
 * {
 *     public string $host = 'localhost';
 *     public bool   $verbose = true;
 * }
 *
 * $o = new MyOptions();
 * echo $o->getOptions(MyOption::class);
 * // → --host "localhost" -verbose
 * ```
 */
abstract class Option
{
    use ConstantsTrait ;

    /**
     * Returns the option keyword derived from a property name.
     *
     * Default implementation: {@see hyphenate()} which converts to kebab-case
     * (e.g., "dryRun" → "dry-run").
     *
     * By overriding this method in a subclass, you can apply a different format
     * depending on the option name.
     *
     * @param string $option Property name (e.g., "dryRun").
     * @return string        By default, transformed option name (e.g., "dry-run").
     */
    public static function getCommandOption( string $option ): string
    {
        return hyphenate( $option ) ;
    }

    /**
     * Returns the prefix to use for a given option.
     *
     * This value is used by {@see Options::getOptions()} and can be:
     * - a string (e.g., "--", "-", "/opt:");
     * - null to indicate not to override the prefix passed to {@see Options::getOptions()}
     *   (which can be a string or a callable).
     *
     * By overriding this method in a subclass, you can apply a different prefix
     * depending on the option name.
     *
     * @param string $option Property/option name.
     * @return ?string       Prefix to use, or null to avoid overriding.
     */
    public static function getCommandPrefix( string $option ): ?string
    {
        return null ;
    }
}