# TOML — `oihana\files\toml`

The **`oihana\files\toml`** module exposes a single function — [`resolveTomlConfig`](#resolvetomlconfig) — to **load a TOML file** and **merge** it with a default configuration.

> 💡 Relies on the [`devium/toml`](https://github.com/vanodevium/toml) dependency (TOML 1.0 compliant). See [Dependencies](../getting-started/dependencies.md#deviumtoml).

## Why TOML?

[TOML](https://toml.io) (Tom's Obvious Minimal Language) is a configuration format that is:

- **human-readable** (more than JSON, comparable to YAML but without indentation pitfalls);
- **typed** (string, int, float, bool, datetime, array, table);
- **strict spec** (1.0 stable) — no ambiguity across implementations;
- **widely adopted** by Rust (`Cargo.toml`), Python (`pyproject.toml`), and many modern tools.

Example TOML file:

```toml
debug = false
timezone = "UTC"

[database]
host = "localhost"
port = 3306
username = "root"

[[servers]]
name = "primary"
host = "10.0.0.1"

[[servers]]
name = "replica"
host = "10.0.0.2"
```

After decoding by `devium/toml`, it becomes a standard PHP associative array.

---

## `resolveTomlConfig`

```php
resolveTomlConfig(
    ?string   $filePath ,
    ?array    $defaultConfig = []   ,
    ?string   $defaultPath   = null ,
    ?callable $init          = null
) : array
```

Full pipeline: **path resolution → assertion → decoding → merge → post-processing**.

### Parameters

| Parameter        | Type        | Effect |
|------------------|-------------|--------|
| `$filePath`      | `?string`   | TOML path. **If `null` or empty → only the default config is used.** |
| `$defaultConfig` | `?array`    | Default values, merged underneath the decoded config (lower priority). |
| `$defaultPath`   | `?string`   | Base directory to resolve relative paths if `$filePath` does not resolve in `getcwd()`. |
| `$init`          | `?callable` | Post-processing applied to the final config. Signature: `fn(array): array`. |

### Path resolution logic

1. **Extension append**: if `$filePath` does not end with `.toml`, the suffix is added automatically (`FileExtension::TOML`).
2. **Relative resolution**: if `$filePath` is not absolute:
   - try [`isBasePath($filePath, getcwd())`](../path/inspection.md#isbasepath) → if OK, `makeAbsolute` from `getcwd()`;
   - otherwise, if `$defaultPath` is provided, join `joinPaths($defaultPath, $filePath)` and test `is_file`. If OK, use that path.
3. **`assertFile`**: verifies the resolved path exists and is readable.

### Merge logic

- **Deep merge** via [`deepMerge`](../getting-started/dependencies.md#oihanaphp-core) — sub-arrays are merged recursively (TOML scalar values overwrite defaults at the same path, but sub-arrays are merged).
- **Precedence**: TOML > default. The file value replaces the default value at the same path.

### `$init` callback logic

If provided, called last with the final config. Must return an `array` — typically used to:
- validate that required keys are present;
- transform some paths (resolve `${VAR}`, expand `~`, etc.);
- enrich with computed data (hostname, version, etc.).

### Exceptions

| Exception | Case |
|---|---|
| `FileException` | Resolved path invalid or file missing. |
| `DirectoryException` | `$defaultPath` provided but not a valid directory. |
| `Devium\Toml\TomlError` | Malformed TOML content. |

> ⚠ The three exceptions are **distinct** — catch them separately to differentiate error kinds.

---

## Complete example

`config/default.toml` (committed to VCS):

```toml
debug = false
timezone = "UTC"

[database]
host = "localhost"
port = 3306
```

`config/local.toml` (not committed):

```toml
debug = true

[database]
host = "db.production.internal"
```

```php
use function oihana\files\toml\resolveTomlConfig;

$defaultConfig = [
    'app' => [
        'name'    => 'MyApp' ,
        'version' => '1.0.0' ,
    ] ,
    'database' => [
        'port'    => 3306 ,
        'timeout' => 30 ,
    ] ,
] ;

$config = resolveTomlConfig(
    'config/local' ,                 // .toml appended automatically
    $defaultConfig ,
    __DIR__ ,                        // base to resolve 'config/local'
    fn( array $cfg ) => $cfg + [     // post-processing: add a computed field
        'hostname' => gethostname() ,
    ] ,
) ;
```

Result (deep merge):

```php
[
    'app' => [
        'name'    => 'MyApp' ,
        'version' => '1.0.0' ,
    ] ,
    'database' => [
        'host'    => 'db.production.internal' ,  // TOML override
        'port'    => 3306 ,                       // kept from default
        'timeout' => 30 ,                         // kept from default
    ] ,
    'debug'    => true ,
    'timezone' => 'UTC' ,
    'hostname' => 'web-01' ,                      // added by $init
]
```

---

## Use case: per-environment config

```php
$env = getenv('APP_ENV') ?: 'dev' ;

$config = resolveTomlConfig(
    "config/env/{$env}" ,           // config/env/dev.toml or prod.toml
    $defaults ,
    __DIR__ ,
) ;
```

## Use case: optional config file

```php
// If the user did not create local.toml, keep the defaults
$config = resolveTomlConfig(
    $userConfigPath ?? null ,        // null → defaults only
    $defaults ,
) ;
```

## Use case: init with validation

```php
$config = resolveTomlConfig(
    'config/app' ,
    $defaults ,
    __DIR__ ,
    function( array $cfg ) {
        // Required keys validation
        foreach ( [ 'database.host' , 'database.port' ] as $required ) {
            $path = explode( '.' , $required ) ;
            $val  = $cfg ;
            foreach ( $path as $key ) {
                if ( !isset( $val[ $key ] ) ) {
                    throw new \RuntimeException( "Missing required config: $required" ) ;
                }
                $val = $val[ $key ] ;
            }
        }
        return $cfg ;
    } ,
) ;
```

---

## Comparison: TOML vs alternatives

| Format  | Readability | Typing | Standard | When to use here |
|---------|-------------|--------|----------|------------------|
| **TOML** | ⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐ | Human config, deeply nested, multi-environment |
| **PHP** | ⭐⭐ | ⭐⭐⭐ | n/a | 100% PHP config, computed expressions → [`requireAndMergeArrays`](../files/reading.md#requireandmergearrays) |
| **JSON** | ⭐ | ⭐⭐ | ⭐⭐⭐ | Not human-friendly (no comments) |
| **YAML** | ⭐⭐ | ⭐⭐ | ⭐⭐ | Readable but ambiguous (indentation) |
| **`.env`** | ⭐⭐⭐ | ⭐ | ⭐⭐ | Flat env variables, no structure |

For `oihana/*`, the default choice is:
- **TOML** for configs versioned in the repo (end user / deployment).
- **PHP via `requireAndMergeArrays`** for framework-internal configs (DI containers, computed mappings).

## See also

- [Reading](../files/reading.md#requireandmergearrays) — `requireAndMergeArrays` for the same pattern in pure PHP.
- [Path](../path/README.md) — `isAbsolutePath`, `isBasePath`, `joinPaths`, `makeAbsolute` used internally.
- [Assertions](../files/assertions.md) — `assertFile`, `assertDirectory`.
- [Dependencies](../getting-started/dependencies.md#deviumtoml) — `devium/toml`, `deepMerge`.
- [Exceptions](../exceptions.md) — `FileException`, `DirectoryException`.
- [English TOC](../README.md).
