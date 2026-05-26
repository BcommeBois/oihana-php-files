# Options — `oihana\options`

The **`oihana\options`** namespace exposes **two abstract classes** that together form a central pattern in `oihana/*`: modelling **strongly-typed configuration objects** that are hydratable, serialisable and convertible to CLI arguments.

- [`Options`](#the-options-class) — abstract class to extend to define a config object.
- [`Option`](#the-option-class) — associated contract that maps **property names** to **CLI option names**.

> 💡 The **`oihana\options`** namespace is to be distinguished from the `oihana\files\options/` directory (which contains **concrete implementations** like `MakeFileOptions` and `OwnershipInfos` — see [Concrete options](make-file-options.md)).

## The Options pattern in brief

**Problem**: passing 10 parameters to a PHP function is unreadable. Passing an associative array loses typing. Building a wrapper class for each case is verbose.

**Solution**: an abstract `Options` class providing automatically:

1. **Hydration** from an array or an object (via the constructor).
2. **Serialisation** to `array` and JSON.
3. **Template formatting** (`'{{host}}:{{port}}'` → `'localhost:8080'`).
4. **CLI argument generation** (`--host "localhost" --port 8080`).
5. **Merging** of multiple config sources (`resolve(...)`).

```php
class ServerOptions extends Options
{
    public string $host = 'localhost' ;
    public int    $port = 8080 ;
    public bool   $debug = false ;
}

$opts = new ServerOptions( [ 'debug' => true ] ) ;

echo $opts->format( 'http://{{host}}:{{port}}' ) ;
// → http://localhost:8080

echo json_encode( $opts ) ;
// → {"host":"localhost","port":8080,"debug":true}
```

---

## The `Options` class

```php
abstract class Options implements ClearableArrayable, Cloneable, JsonSerializable
```

Implements 3 interfaces from [`oihana/php-enums`](../getting-started/dependencies.md#oihanaphp-enums):

- `Arrayable` (via `ClearableArrayable`) — `toArray()` contract.
- `ClearableArrayable` — `toArray(bool $clear)` contract.
- `Cloneable` — `clone(): static` contract.
- `JsonSerializable` (native PHP) — for `json_encode()`.

Uses the `ReflectionTrait` (from [`oihana/php-reflect`](../getting-started/dependencies.md#oihanaphp-reflect)) to enumerate public properties.

### `__construct( array|object|null $init = null )`

**Automatic hydration** from an array or an object. Only **declared** public properties on the class are updated — unknown keys are silently ignored.

```php
class MyOptions extends Options
{
    public string $name = '' ;
    public int    $count = 0 ;
}

$o = new MyOptions( [
    'name'    => 'foo' ,
    'count'   => 42 ,
    'unknown' => 'ignored' , // silently ignored
] ) ;

// Also: object as input
$o = new MyOptions( $somestdClass ) ;
```

### `create( array|Options|null $options = null ) : Options` (static)

Flexible **factory**:

- Array → new instance with hydration.
- Existing instance → returned as-is (no copy).
- `null` → new empty instance.

```php
$o = MyOptions::create( [ 'name' => 'foo' ] ) ; // new MyOptions(['name'=>'foo'])
$o = MyOptions::create( $existing ) ;            // === $existing
$o = MyOptions::create() ;                       // new MyOptions()
```

### `clone() : static`

**Deep copy** via `serialize`/`unserialize`. Use it when you want to duplicate an Options without risking a shared reference on nested arrays/objects.

```php
$base   = new ServerOptions( [ 'host' => 'localhost' ] ) ;
$custom = $base->clone() ;
$custom->host = 'remote' ;
// $base->host is still 'localhost'
```

### `toArray( bool $clear = false ) : array`

Converts the object to an associative array via public properties.

**If `$clear: true`**:
- empty strings (`''` after `trim`) become `null`;
- empty arrays (`[]`) become `null`;
- `null` entries are **filtered** from the result.

```php
$o = new ServerOptions( [
    'host'  => 'localhost' ,
    'port'  => 8080 ,
    'debug' => null ,
    'empty' => '' ,
] ) ;

$o->toArray() ;
// [ 'host' => 'localhost', 'port' => 8080, 'debug' => null, 'empty' => '', ... ]

$o->toArray( true ) ;
// [ 'host' => 'localhost', 'port' => 8080 ]
```

### `jsonSerialize() : object`

**JSON** serialisation via `json_encode`. Returns an **`object`** (not an `array`) → guarantees `json_encode` produces `{}` even when the result is empty (and not `[]`).

```php
echo json_encode( new ServerOptions( [ 'host' => '' , 'debug' => null ] ) ) ;
// → {}  (not [])
```

Uses `toArray(true)` internally — empty values are filtered out.

### `format( ?string $template , string $prefix = '{{' , string $suffix = '}}' , ?string $pattern = null ) : ?string`

**Formats a template** by replacing `{{prop}}` placeholders with the object's property values.

```php
$o = new ServerOptions( [ 'host' => 'example.com' , 'port' => 443 ] ) ;

echo $o->format( 'https://{{host}}:{{port}}' ) ;
// → https://example.com:443

// Custom delimiters
echo $o->format( 'Hello %%host%%!' , '%%' , '%%' ) ;
// → Hello example.com!

// Missing property → replaced by empty string
echo $o->format( 'X: {{nonexistent}}' ) ;
// → X:
```

### `formatArray( array &$data , array|object|null $source = null , ... ) : array`

**Recursively formats** all `string` values in an array using the object as placeholder source (or an external `$source`).

Modifies `$data` **by reference**.

```php
$o = new ServerOptions( [ 'host' => 'example.com' , 'apiVersion' => 'v1' ] ) ;

$payload = [
    'base' => 'https://{{host}}/api/{{apiVersion}}' ,
    'endpoints' => [
        'users' => 'https://{{host}}/api/{{apiVersion}}/users' ,
    ] ,
    'unchanged' => 42 ,
] ;

$o->formatArray( $payload ) ;

// $payload =
// [
//     'base'      => 'https://example.com/api/v1' ,
//     'endpoints' => [ 'users' => 'https://example.com/api/v1/users' ] ,
//     'unchanged' => 42 ,
// ]
```

### `formatFromDocument( array|object $document , ... ) : void`

**Inverse** of `format`: formats the object's **public string properties** using an external document as placeholder source.

```php
$o = new ServerOptions() ;
$o->url = 'https://{{host}}/{{path}}' ;

$o->formatFromDocument( [ 'host' => 'example.com' , 'path' => 'docs' ] ) ;

echo $o->url ;
// → https://example.com/docs
```

> 💡 Useful to expand templates **after** hydration with environment variables for instance.

### `getOptions( ?string $clazz , ... ) : string`

**Conversion to a CLI argument string**. The heart of the Options pattern for integration with Symfony Console, shell scripts, exec, etc.

```php
class MyOption extends Option
{
    public const string HOST    = 'host' ;
    public const string PORT    = 'port' ;
    public const string VERBOSE = 'verbose' ;
}

class MyOptions extends Options
{
    public string $host    = 'localhost' ;
    public int    $port    = 8080 ;
    public bool   $verbose = true ;
}

$opts = new MyOptions() ;
echo $opts->getOptions( MyOption::class ) ;
// → --host "localhost" --port 8080 --verbose
```

#### `getOptions` parameters

| Parameter        | Type                            | Default   | Effect |
|------------------|---------------------------------|-----------|--------|
| `$clazz`         | `?string` (class-string<Option>) | `null`   | `Option` class to use for the mapping. Without it, returns `''`. |
| `$prefix`        | `callable\|string\|null`        | `'--'`    | Default prefix, or callback `fn(string $name): string`. |
| `$excludes`      | `?array`                        | `null`    | Properties to ignore. |
| `$separator`     | `callable\|string`              | `' '`     | Separator between option and value (space, `=`, etc.), or callback. |
| `$order`         | `?array`                        | `null`    | Forced order for some properties. |
| `$reverseOrder`  | `bool`                          | `false`   | If `true`, ordered properties go to the end. |

#### Per-type behaviour

| Value type | Output |
|---|---|
| `null` | property **ignored**. |
| `true` | flag option: `--verbose` (no value). |
| `array` | option **repeated**: `--list "a" --list "b"`. |
| other (string, int, bool=false) | `--option "value"` with `json_encode` on the value. |

#### Advanced example: mixed prefixes and separators

```php
$opts->getOptions(
    MyOption::class ,
    prefix: fn( string $name ) => match( $name )
    {
        'foo'     => '--' ,
        'verbose' => '-' ,
        'list'    => '/opt:' ,
        default   => '' ,
    } ,
    excludes: [ 'internalFlag' ] ,
    separator: fn( string $name ) => $name === 'list' ? '=' : ' ' ,
) ;
// → --foo "value" -verbose /opt:list="a" /opt:list="b"
```

#### Forced order

```php
$opts->getOptions(
    MyOption::class ,
    order: [ 'verbose' , 'foo' ] ,         // 'verbose' and 'foo' first
    reverseOrder: false ,                  // (true → at the end)
) ;
```

### `resolve( ...$sources ) : static` (static)

**Merges** several config sources into a single instance. Precedence: **last source > first**.

Accepts:
- **Arrays** → direct merge.
- **`Options` instances** (or any `Arrayable` / `ClearableArrayable`) → converted via `toArray()` (or `toArray(true)` for `ClearableArrayable`).
- **`null`** → ignored.

```php
$defaults = new ServerOptions( [ 'host' => 'localhost' , 'port' => 8080 ] ) ;
$user     = [ 'port' => 8888 , 'debug' => true ] ;
$override = new ServerOptions( [ 'debug' => false ] ) ;

$final = ServerOptions::resolve( $defaults , $user , $override ) ;
// host = localhost, port = 8888, debug = false
```

**Throws `InvalidArgumentException`** if a source is not `array`, `Options`, `Arrayable`/`ClearableArrayable`, nor `null`.

### `__toString() : string`

Returns `''` by default. **Override** in concrete classes for a meaningful output (e.g. `MakeFileOptions` returns the file path).

---

## The `Option` class

```php
abstract class Option
```

**Contract** used by `Options::getOptions()` to map property names to CLI option names. No properties — just two overridable static methods.

Uses the `ConstantsTrait` (from `oihana/php-reflect`) to expose its constants via reflection.

### `getCommandOption( string $option ) : string` (static)

**Transforms** the property name into a CLI option name. Default implementation: `hyphenate()` (from `oihana/php-core`) → kebab-case.

```php
Option::getCommandOption( 'dryRun' ) ;     // → 'dry-run'
Option::getCommandOption( 'apiKey' ) ;     // → 'api-key'
Option::getCommandOption( 'maxRetries' ) ; // → 'max-retries'
```

**Override** for another convention:

```php
class MyOption extends Option
{
    public static function getCommandOption( string $option ): string
    {
        return strtoupper( $option ) ; // → 'DRYRUN', 'APIKEY'
    }
}
```

### `getCommandPrefix( string $option ) : ?string` (static)

**Per-option prefix**. Returns `null` by default → uses the global prefix passed to `getOptions()`.

**Override** to differentiate short/long flags or alternative notations:

```php
class MyOption extends Option
{
    public const string VERBOSE = 'verbose' ;
    public const string HOST    = 'host' ;

    public static function getCommandPrefix( string $option ): ?string
    {
        return match( $option )
        {
            self::VERBOSE => '-' ,    // -verbose (short)
            default       => '--' ,   // --host (long)
        } ;
    }
}
```

---

## Full pattern: end-to-end example

```php
use oihana\options\Option;
use oihana\options\Options;

// 1. Define the CLI mapping
class RsyncOption extends Option
{
    public const string ARCHIVE     = 'archive' ;
    public const string VERBOSE     = 'verbose' ;
    public const string DRY_RUN     = 'dryRun' ;
    public const string EXCLUDE     = 'exclude' ;
    public const string DESTINATION = 'destination' ;
}

// 2. Define the options DTO
class RsyncOptions extends Options
{
    public bool    $archive     = true ;
    public bool    $verbose     = false ;
    public bool    $dryRun      = false ;
    public ?array  $exclude     = null ;
    public ?string $destination = null ;
}

// 3. Hydrate from user input (HTTP, CLI, config file…)
$rsync = RsyncOptions::resolve(
    [ 'archive' => true , 'verbose' => true ] ,           // business defaults
    parse_ini_file( '/etc/myapp/rsync.ini' ) ?: [] ,      // user config
    [ 'dryRun' => $_GET[ 'dryRun' ] ?? false ] ,          // request override
) ;

// 4. Build the shell command
$cmd = 'rsync ' . $rsync->getOptions( RsyncOption::class ) ;
// → rsync --archive --verbose --dry-run --exclude "..." --destination "..."

// 5. (Optional) Serialise for log or audit
file_put_contents( '/var/log/rsync.json' , json_encode( $rsync ) ) ;
```

## See also

- [Concrete options](make-file-options.md) — `MakeFileOptions`, `OwnershipInfos` (codebase examples).
- [Dependencies](../getting-started/dependencies.md) — `oihana/php-reflect` (`ReflectionTrait`), `oihana/php-enums` (interfaces), `oihana/php-core` (`hyphenate`, `formatDocument`).
- [Creation](../files/creation.md) — `makeFile` accepts a `MakeFileOptions` as an alternative to positional parameters.
- [System](../files/system.md#getownershipinfos) — `getOwnershipInfos` returns an `OwnershipInfos` instance.
- [English TOC](../README.md).
