<?php

namespace oihana\options;

use InvalidArgumentException;
use JsonSerializable;
use ReflectionException;

use oihana\enums\Char;
use oihana\interfaces\Arrayable;
use oihana\interfaces\ClearableArrayable;
use oihana\interfaces\Cloneable;
use oihana\reflect\traits\ReflectionTrait;

use function oihana\core\documents\formatDocument;
use function oihana\core\strings\formatFromDocument;

/**
 * Abstract base class for defining configurable options.
 *
 * Features:
 * - Automatic hydration from arrays or objects.
 * - Reflection-based listing of public properties.
 * - Template string formatting with placeholders.
 * - CLI-compatible string generation from object state.
 */
abstract class Options implements ClearableArrayable , Cloneable , JsonSerializable
{
    /**
     * Initializes the object using an associative array or object.
 *
     * Only public properties declared on the class will be set.
     * Unknown or non-public properties are silently ignored.
     *
     * @param array|object|null $init Initial values to populate the instance.
     */
    public function __construct( array|object|null $init = null )
    {
        if( isset( $init ) )
        {
            foreach ( $init as $key => $value )
            {
                if( property_exists( $this , $key ) )
                {
                    $this->{ $key } = $value ;
                }
            }
        }
    }

    use ReflectionTrait ;

    /**
     * Creates a deep copy of the current instance.
     *
     * This performs a full deep copy by serializing and unserializing the object.
     * Useful when duplicating options to avoid shared references.
     *
     * @return static A new cloned instance.
     */
    public function clone(): static
    {
        return unserialize( serialize( $this ) );
    }

    /**
     * Instantiates the class from an array or another Options instance.
     *
     * - If $options is an array, it is passed to the constructor.
     * - If $options is already an Options instance, it is returned as-is.
     * - If null, a new empty instance is returned.
     *
     * @param array|Options|null $options  The initial values or existing options instance.
     * @return static                      A new or reused instance of the called class.
     */
    public static function create( array|Options|null $options = null ) :Options
    {
        if( is_array( $options ) )
        {
            return new static( $options ) ;
        }
        return $options instanceof Options ? $options : new static() ;
    }

    /**
     * Formats a template string by replacing placeholders like `{{property}}` with
     * the corresponding public property values of the current object.
     *
     * Supports custom placeholder delimiters. If a referenced property is not defined
     * or is null, it is replaced with an empty string.
     *
     * @param string|null $template The template string. Placeholders must match the format `{{property}}` or a custom format.
     * @param string $prefix The prefix that begins a placeholder (default: `{{`).
     * @param string $suffix The suffix that ends a placeholder (default: `}}`).
     * @param string|null $pattern Optional full regex pattern to match placeholders (including delimiters).
     *
     * @return string|null The formatted string, or `null` if the template is invalid.
     *
     * @throws ReflectionException
     *
     * @example
     * ```php
     * $opts = new ServerOptions();
     * $opts->domain    = 'example.com';
     * $opts->subdomain = 'www';
     *
     * echo $opts->format('https://{{subdomain}}.{{domain}}');
     * // → https://www.example.com
     *
     * echo $opts->format('Hello %%domain%%!', '%%', '%%');
     * // → Hello example.com!
     *
     * echo $opts->format('Missing: {{nonexistent}}');
     * // → Missing:
     * ```
     */
    public function format( ?string $template = null , string $prefix = '{{' , string $suffix = '}}' , ?string $pattern = null ): ?string
    {
        if ( !is_string( $template ) || $template === Char::EMPTY )
        {
            return null;
        }
        return formatFromDocument( $template , $this->toArray() , $prefix , $suffix , pattern: $pattern ) ;
    }

    /**
     * Recursively formats all string values in an array using internal or external values.
     *
     * - If $source is null, the object itself is used as the placeholder provider (via `$this->format()`).
     * - If $source is provided (array or object), it is used via `formatFromDocument()`.
     *
     * @param array             &$data     The input array to be formatted (by reference).
     * @param array|object|null $source    External document used to resolve placeholders. If null, use `$this`.
     * @param string            $prefix    Placeholder prefix (default `{{`).
     * @param string            $suffix    Placeholder suffix (default `}}`).
     * @param string            $separator Separator used in keys (default `.`).
     * @param string|null       $pattern   Optional custom placeholder regex.
     *
     * @return array The formatted array.
     */
    public function formatArray
    (
        array &$data,
        array|object|null $source = null,
        string $prefix = '{{',
        string $suffix = '}}',
        string $separator = '.',
        ?string $pattern = null
    ): array
    {
        $formatter = $source === null
            ? fn(
                mixed $val,
                array|object $root,
                string $prefix,
                string $suffix,
                string $separator,
                ?string $pattern
            ) => $this->format($val, $prefix, $suffix)
            : null;

        $formatted = formatDocument( $data, $prefix, $suffix, $separator, $pattern, $formatter ) ;
        $data = is_array( $formatted ) ? $formatted : (array) $formatted;
        return $data;
    }

    /**
     * Formats all public string properties using external data instead of internal values.
     *
     * @param array|object $document Associative array or an object of placeholder values.
     * @param string       $prefix   Placeholder prefix (default `{{`).
     * @param string       $suffix   Placeholder suffix (default `}}`).
     *
     * @return void
     *
     * @throws ReflectionException
     * @example
     * ```php
     * $opts->url = 'https://{{host}}/{{path}}';
     * $opts->formatFromDocument(['host' => 'example.com', 'path' => 'docs']);
     * echo $opts->url; // → https://example.com/docs
     * ```
     */
    public function formatFromDocument( array|object $document , string $prefix = '{{' , string $suffix = '}}' ): void
    {
        foreach ( $this->getPublicProperties( static::class ) as $property )
        {
            $name     = $property->getName() ;
            $template = $this->{ $name } ?? null ;

            if ( is_string( $template ) && str_contains( $template , $prefix ) && str_contains( $template , $suffix ) )
            {
                $this->{ $name } = formatFromDocument( $template , $document , $prefix , $suffix );
            }
        }
    }

    /**
     * Returns a string representing the current options formatted as CLI arguments.
     *
     * This method converts the properties of the current options object into a list of
     * command-line arguments, using a configurable prefix and separator for each property.
     *
     * You can pass a string or a callable to dynamically generate prefixes or separators based on the property name.
     *
     * @param class-string         $clazz         Class implementing the getCommandOption(string $property): string method.
     * @param null|callable|string $prefix        Prefix for each option (e.g. '--', '-', '/opt:'), or a callable (string $property): string.
     * @param array<string>        $excludes      Optional list of property names to exclude from the output.
     * @param callable|string      $separator     Separator between option and value (default is a space), or a callable (string $property): string.
     * @param array<string>        $order         Optional list of property names to force order.
     * @param bool                 $reverseOrder  If true, ordered properties are placed at the end instead of the beginning.
     *
     * @return string CLI-formatted options string, e.g. '--foo "bar" -v --list "one" --list "two"'
     *
     * @throws ReflectionException
     *
     * @example
     * ```php
     * class MyOption extends Option
     * {
     *     public const string FOO     = 'foo' ;
     *     public const string LIST    = 'list' ;
     *     public const string VERBOSE = 'verbose' ;
     * }
     *
     * class MyOptions extends Options
     * {
     *     public string $foo     = 'value';
     *     public bool   $verbose = true;
     *     public array  $list    = ['a', 'b'];
     *
     *     public string $internalFlag = 'hello' ;
     * }
     *
     * $options = new MyOptions();
     *
     * $result = $options->getOptions
     * (
     *     MyOption::class ,
     *     prefix : fn( string $name ) => match($name)
     *    {
     *         MyOption::FOO     => '--' ,
     *         MyOption::VERBOSE => '-' ,
     *         MyOption::LIST    => '/opt:' ,
     *         default           => '' ,
     *     },
     *     excludes : [ 'internalFlag' ] ,
     *     separator : fn( string $name ) => $name === 'list' ? '=' : ' '
     * );
     *
     * echo $result;
     * // Output:
     * // --foo "value" -verbose /opt:list="a" /opt:list="b"
     * ```
     *
     * Use the order parameter :
     * ```php
     * $options->getOptions
     * (
     *    MyOption::class,
     *    prefix: fn($name) => match ($name)
     *    {
     *       'foo' => '--',
     *       'verbose' => '-',
     *        default => '/opt:'
     *    },
     *    excludes  : ['internal'],
     *    separator : fn($name) => $name === 'list' ? '=' : ' ',
     *    order: ['verbose', 'foo'],
     *    reverseOrder: false // Place these first
     * );
     * ```
     */
    public function getOptions
    (
        ?string              $clazz        = null ,
        callable|string|null $prefix       = Char::DOUBLE_HYPHEN ,
        ?array               $excludes     = null ,
        callable|string      $separator    = Char::SPACE ,
        ?array               $order        = null ,
        bool                 $reverseOrder = false
    )
    :string
    {
        if( !isset( $clazz ) )
        {
            return Char::EMPTY ;
        }

        if ( !is_a( $clazz, Option::class , true ) )
        {
            throw new InvalidArgumentException( sprintf
            (
                __METHOD__ . " failed, the passed-in class %s must inherit the Option class." ,
                $clazz
            )) ;
        }

        $expression = [] ;

        $properties = $this->getPublicProperties( static::class ) ;

        if( is_array( $order ) && !empty( $order ) )
        {
            usort($properties, function( $a , $b ) use ( $order , $reverseOrder )
            {
                $aIndex = array_search( $a->getName() , $order ) ;
                $bIndex = array_search( $b->getName() , $order ) ;

                $aIndex = $aIndex === false ? ( $reverseOrder ? -1 : PHP_INT_MAX ) : $aIndex ;
                $bIndex = $bIndex === false ? ( $reverseOrder ? -1 : PHP_INT_MAX ) : $bIndex ;

                return $aIndex <=> $bIndex;
            });
        }

        foreach( $properties as $property )
        {
            $name = $property->getName() ;

            if ( is_array( $excludes ) && in_array( $name , $excludes , true ) )
            {
                continue ;
            }

            $value = $this->{ $name } ?? null ;

            if ( !isset( $value ) )
            {
                continue;
            }

            $option = $clazz::getCommandOption( $name ) ;
            $prefix = $clazz::getCommandPrefix( $name ) ?? $prefix ;

            if( isset( $prefix ) )
            {
                $resolvedPrefix = is_callable( $prefix ) ? $prefix($name) : $prefix;
                if( is_string( $resolvedPrefix ) && $resolvedPrefix !== Char::EMPTY )
                {
                    $option = $resolvedPrefix . $option ;
                }
            }

            $resolvedSeparator = is_callable( $separator ) ? $separator( $name ) : $separator ;
            if ( !is_string( $resolvedSeparator ) )
            {
                $resolvedSeparator = Char::SPACE;
            }

            if( is_array( $value ) && count( $value ) > 0 )
            {
                foreach ( $value as $item )
                {
                    $expression[] = $option . $resolvedSeparator . json_encode( $item , JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ;
                }
            }
            elseif ( $value === true )
            {
                $expression[] = $option ;
            }
            else
            {
                $expression[] = $option . $resolvedSeparator . json_encode( $value , JSON_UNESCAPED_SLASHES ) ;
            }
        }

        return implode( Char::SPACE , $expression ) ;
    }

    /**
     * Returns data to be serialized by json_encode().
     *
     * This implementation converts the cleaned public properties of the object
     * to an object representation, ensuring that json_encode always returns
     * a JSON object (e.g. `{}` instead of `[]` when empty).
     *
     * @return object An object representing the public non-empty properties.
     *
     * @throws ReflectionException
     * @example
     * @example
     * ```php
     * $options = new ServerOptions
     * ([
     *     'host' => 'localhost',
     *     'port' => 8080,
     *     'debug' => null,
     * ]);
     *
     * echo json_encode($options);
     * // → {"host":"localhost","port":8080}
     *
     * $options = new ServerOptions([ 'host' => '' , 'debug' => null ]);
     *
     * echo json_encode($options); // → {}
     */
    public function jsonSerialize(): object
    {
        return (object) $this->toArray(true) ;
    }

    /**
     * Resolves options by merging multiple configuration sources.
     *
     * This method accepts multiple sources of configuration and merges them in order to create a final options object.
     *
     * Sources can be:
     * - Associative arrays
     * - Options classes, and generally ClearableArrayable or Arrayable classes ( which will be converted to arrays via toArray() ).
     * - null values (which will be ignored).
     *
     * The sources are merged in the order they are provided, with later sources
     * overriding earlier ones for conflicting keys.
     *
     * @param mixed ...$sources Variable number of configuration sources.
     *                                      Each can be an array, Options instance, or null.
     *
     * @return static An instance of the calling Options class with merged configuration.
     *
     * @throws InvalidArgumentException If a source is not a valid type.
     *
     * @example
     * ```php
     * // Merge multiple arrays
     * $options = MyOptions::resolve
     * (
     *     [ 'host' => 'localhost', 'port' => 8080],
     *     [ 'debug' => true ],
     *     [ 'port' => 9000  ] // This will override the previous port value
     * );
     *
     * // Merge arrays and Options instances
     * $defaultOptions = new MyOptions( ['host' => 'localhost'] ) ;
     * $userOptions    = ['port' => 8080 , 'debug' => true ] ;
     * $overrides      = new MyOptions( [ 'debug' => false ] ) ;
     *
     * $finalOptions = MyOptions::resolve( $defaultOptions , $userOptions , $overrides ) ;
     * ```
     */
    public static function resolve( mixed ...$sources ) :static
    {
        $options = [] ;

        if ( empty( $sources ) )
        {
            return new static() ;
        }

        $sources = array_filter( $sources , fn( $value ) => $value !== null ) ;

        foreach ( $sources as $source )
        {
            $overrides = null ;

            if ( is_array( $source ) )
            {
                $overrides = $source ;
            }
            elseif ( $source instanceof Arrayable )
            {
                $overrides = $source->toArray() ;
            }
            elseif ( $source instanceof ClearableArrayable )
            {
                $overrides = $source->toArray( true ) ;
            }
            else
            {
                throw new InvalidArgumentException( sprintf
                (
                    'Invalid source type provided to %s::resolve(). Expected array, Options instance, or null, got %s.' ,
                    static::class ,
                    get_debug_type( $source )
                ) ) ;
            }

            $options = array_merge( $options , $overrides ) ;
        }

        return static::create( $options ) ;
    }

    /**
     * Converts the current object to an associative array.
     *
     * Only public properties defined on the class are included.
     * Useful for serialization, debugging, or exporting the object state.
     *
     * @param bool $clear If true, removes entries with null values. Default: false.
     *
     * @return array<string, mixed> The associative array representation of the object.
     *
     * @throws ReflectionException
     * @example
     * ```php
     * $options = new ServerOptions([
     *     'host' => 'localhost',
     *     'port' => 8080,
     *     'debug' => null,
     *     'empty' => '',
     *     'values' => [],
     * ]);
     *
     * print_r($options->toArray());
     * // → [ 'host' => 'localhost', 'port' => 8080, 'debug' => null , 'empty' => '' , 'values' => [] ]
     *
     * print_r($options->toArray(true));
     * // → [ 'host' => 'localhost', 'port' => 8080 ]
     * ```
     */
    public function toArray( bool $clear = false ): array
    {
        $data = [];
        foreach ( $this->getPublicProperties(static::class ) as $property )
        {
            $name  = $property->getName() ;
            $value = $this->{ $name } ?? null ;

            if( ( is_string( $value ) && trim($value) === Char::EMPTY ) || $value === [] )
            {
                $value = null ;
            }

            if ( !$clear || $value !== null )
            {
                $data[ $name ] = $value ;
            }
        }
        return $data ;
    }

    /**
     * Returns a string representation of the object.
     *
     * Override this method in child classes to provide a meaningful string output.
     *
     * @return string  Default implementation returns an empty string.
     */
    public function __toString() : string
    {
        return Char::EMPTY ;
    }
}